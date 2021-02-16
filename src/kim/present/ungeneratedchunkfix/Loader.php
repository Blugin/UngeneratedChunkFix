<?php
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