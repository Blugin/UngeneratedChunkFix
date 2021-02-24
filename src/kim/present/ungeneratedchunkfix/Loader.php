<?php

/**
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author  PresentKim (debe3721@gmail.com)
 * @link    https://github.com/PresentKim
 * @license https://www.gnu.org/licenses/lgpl-3.0 LGPL-3.0 License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpIllegalPsrClassPathInspection
 * @noinspection SpellCheckingInspection
 * @noinspection PhpDocSignatureInspection
 */

declare(strict_types=1);

namespace kim\present\ungeneratedchunkfix;

use pocketmine\block\BlockLegacyIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\EntityDataHelper;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\SubChunk;
use pocketmine\world\World;

final class Loader extends PluginBase implements Listener{
    private Chunk $tempChunk;

    /** @noinspection PhpInternalEntityUsedInspection */
    protected function onLoad() : void{
        //Prepare temporary chunk
        $subchunks = [];
        for($y = 0; $y < Chunk::MAX_SUBCHUNKS; ++$y){
            $subchunks[$y] = new SubChunk(BlockLegacyIds::INVISIBLE_BEDROCK << 4, []);
        }

        $this->tempChunk = new Chunk($subchunks);
        $this->tempChunk->setFullBlock(0, 0, 0, VanillaBlocks::INVISIBLE_BEDROCK()->getFullId());
        $this->tempChunk->setPopulated();
    }

    protected function onEnable() : void{
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /**
     * When the player object is create, prepare chunk at that position
     *
     * @priority LOWEST
     */
    public function onPlayerCreation(PlayerCreationEvent $event) : void{
        $namedtag = $this->getServer()->getOfflinePlayerData($event->getNetworkSession()->getPlayerInfo()->getUsername());
        $worldManager = $this->getServer()->getWorldManager();

        if($namedtag !== null && ($world = $worldManager->getWorldByName($namedtag->getString("Level", ""))) !== null){
            $vec = EntityDataHelper::parseVec3($namedtag, "Pos", false);
        }else{
            $world = $worldManager->getDefaultWorld();

            //Prevents an exception thrown when a get safe spawn an ungenerated world
            $this->prepareChunk($world, $world->getSpawnLocation());
            $vec = $world->getSafeSpawn();
        }
        $this->prepareChunk($world, $vec);
    }

    /**
     * When the player teleport, prepare chunk at that position
     *
     * @priority LOWEST
     */
    public function onEntityTeleport(EntityTeleportEvent $event) : void{
        if($event->getEntity() instanceof Player){
            $to = $event->getTo();
            $this->prepareChunk($to->getWorld(), $to);
        }
    }

    /**
     * Prepare chunk at provided position.
     * A temporary chunk is allocated after requesting to populate chunk.
     */
    private function prepareChunk(World $world, Vector3 $vec) : void{
        $chunkX = $vec->getFloorX() >> 4;
        $chunkZ = $vec->getFloorZ() >> 4;
        if($world->loadChunk($chunkX, $chunkZ) !== null)
            return;

        $world->orderChunkPopulation($chunkX, $chunkZ);
        $world->setChunk($chunkX, $chunkZ, clone $this->tempChunk, false);
    }
}