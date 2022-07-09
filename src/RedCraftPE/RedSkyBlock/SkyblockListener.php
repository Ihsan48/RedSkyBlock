<?php

namespace RedCraftPE\RedSkyBlock;

use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\player\Player;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

//import events:
//block events:
use pocketmine\event\block\BlockFormEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
//player events:
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerDropItemEvent;
//entity events:
use pocketmine\event\entity\EntityTeleportEvent;

use RedCraftPE\RedSkyBlock\Utils\ZoneManager;
use RedCraftPE\RedSkyBlock\Island;

class SkyBlockListener implements Listener {

  private $plugin;

  public function __construct(SkyBlock $plugin) {

    $this->plugin = $plugin;
    $plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
  }

  public function onForm(BlockFormEvent $event) {

    $plugin = $this->plugin;
    $block = $event->getBlock();
    $world = $block->getPosition()->getWorld()->getFolderName();

    $generatorOres = $plugin->cfg->get("Generator Ores", []);
    $masterWorld = $plugin->skyblock->get("Master World");

    if ($world === $masterWorld || $world === $masterWorld . "-Nether") {

      if (count($generatorOres) === 0) {

        return;
      } else {

        if (array_sum($generatorOres) !== 100) {

          $message = $plugin->mShop->construct("GEN_FORMAT");
          $plugin->getLogger()->info($message);
          return;
        } else {

          $event->cancel();

          $blockID;
          $randomNumber = rand(1, 100);
          $percentChance = 0;

          foreach ($generatorOres as $key => $oreChance) {

            $percentChance += $oreChance;

            if ($randomNumber <= $percentChance) {

              $blockID = $key;
              break;
            }
          }
          $block->getPosition()->getWorld()->setBlock($block->getPosition(), BlockFactory::getInstance()->get($blockID, 0));
          return;
        }
      }
    } else {

      return;
    }
  }

  public function onJoin(PlayerJoinEvent $event) {

    $plugin = $this->plugin;
    $player = $event->getPlayer();
    $spawn = $plugin->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn();
    ZoneManager::clearZoneTools($player);

    if ($plugin->cfg->get("Spawn Command") === "on") {

      $player->teleport($spawn);
    }
  }

  public function onQuit(PlayerQuitEvent $event) {

    $player = $event->getPlayer();
    $zoneShovel = ZoneManager::getZoneShovel();
    $spawnFeather = ZoneManager::getSpawnFeather();

    if (ZoneManager::getZoneKeeper() === $player) {

      ZoneManager::setZoneKeeper();
      ZoneManager::clearZoneTools($player);
    }
  }

  public function onDrop(PlayerDropItemEvent $event) {

    $item = $event->getItem();
    $player = $event->getPlayer();
    $zoneShovel = ZoneManager::getZoneShovel();
    $spawnFeather = ZoneManager::getSpawnFeather();

    if ($item->equals($zoneShovel) || $item->equals($spawnFeather)) {

      $event->cancel();
      $index = array_search($item, $player->getInventory()->getContents());
      $player->getInventory()->setItem($index, VanillaItems::AIR());
    }
  }

  public function onInteract(PlayerInteractEvent $event) {

    $plugin = $this->plugin;
    $player = $event->getPlayer();
    $block = $event->getBlock();
    $item = $event->getItem();
    $action = $event->getAction();
    $zoneShovel = ZoneManager::getZoneShovel();

    $blockPos = $block->getPosition();
    $blockX = round($blockPos->x);
    $blockY = round($blockPos->y);
    $blockZ = round($blockPos->z);

    $zoneWorld = ZoneManager::getZoneWorld();
    $blockWorld = $blockPos->world;

    // check if using a zonetool and take appropriate actions if true:

    if ($item->equals($zoneShovel)) {

      if ($action === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {

        ZoneManager::setFirstPosition($blockPos);

        if ($zoneWorld === null || $zoneWorld != $blockWorld) {

          ZoneManager::setZoneWorld($blockWorld);
          ZoneManager::setSecondPosition(); //reset the other position because it was selected in a different world
          $zoneWorld = $blockWorld;
        }

        $message = $plugin->mShop->construct("SET_POS1");
        $message = str_replace("{X}", $blockX, $message);
        $message = str_replace("{Y}", $blockY, $message);
        $message = str_replace("{Z}", $blockZ, $message);
        $message = str_replace("{ZWORLD}", $zoneWorld->getFolderName(), $message);
        $player->sendMessage($message);
        return;
      }
    }
  }

  public function onBreak(BlockBreakEvent $event) {

    $plugin = $this->plugin;
    $player = $event->getPlayer();
    $block = $event->getBlock();
    $item = $event->getItem();
    $zoneShovel = ZoneManager::getZoneShovel();
    $spawnFeather = ZoneManager::getSpawnFeather();

    $blockPos = $block->getPosition();
    $blockX = round($blockPos->x);
    $blockY = round($blockPos->y);
    $blockZ = round($blockPos->z);

    $zoneWorld = ZoneManager::getZoneWorld();
    $blockWorld = $blockPos->world;

    // check if using a zone tool and take the appropriate actions if true:

    if ($item->equals($zoneShovel)) {

      ZoneManager::setSecondPosition($blockPos);
      $event->cancel();

      if ($zoneWorld === null || $zoneWorld != $blockWorld) {

        ZoneManager::setZoneWorld($blockWorld);
        ZoneManager::setFirstPosition(); //reset the other position because it was selected in a different world
        $zoneWorld = $blockWorld;
      }

      $message = $plugin->mShop->construct("SET_POS2");
      $message = str_replace("{X}", $blockX, $message);
      $message = str_replace("{Y}", $blockY, $message);
      $message = str_replace("{Z}", $blockZ, $message);
      $message = str_replace("{ZWORLD}", $zoneWorld->getFolderName(), $message);
      $player->sendMessage($message);
      return;

    } elseif ($item->equals($spawnFeather)) {

      $event->cancel();
      $zonePos1 = ZoneManager::getFirstPosition();
      $zonePos2 = ZoneManager::getSecondPosition();

      if ($zonePos1 !== null && $zonePos2 !== null) {

        if ($blockWorld === $zoneWorld) {

          ZoneManager::setSpawnPosition($blockPos);
          ZoneManager::createZone();
          ZoneManager::setZoneKeeper();
          ZoneManager::setFirstPosition();
          ZoneManager::setSecondPosition();
          ZoneManager::clearZoneTools($player);

          $message = $plugin->mShop->construct("SET_CUSTOM_SPAWN");
          $player->sendMessage($message);
          return;
        } else {

          $message = $plugin->mShop->construct("WRONG_WORLD");
          $player->sendMessage($message);
          return;
        }
      } else {

        $message = $plugin->mShop->construct("SPAWN_FEATHER_NOT_READY");
        $player->sendMessage($message);
        return;
      }
    }

    // Check if allowed to break blocks or if island value should decrease if on an island:
    $masterWorld = $plugin->islandManager->getMasterWorld();

    if ($masterWorld === $blockWorld) {

      $island = $plugin->islandManager->getIslandAtBlock($block);
      if ($island instanceof Island) {

        $members = $island->getMembers();
        $creator = $island->getCreator();
        $playerName = $player->getName();
        $playerNameLower = strtolower($playerName);

        if (array_key_exists($playerNameLower, $members) || $playerName === $creator || $player->hasPermission("redskyblock.bypass")) {

          $valuableArray = $plugin->cfg->get("Valuable Blocks", []);
          $blockID = $block->getID();
          if (array_key_exists(strval($blockID), $valuableArray)) {

            $island->removeValue((int) $valuableArray[strval($blockID)]);
          }
        } else {

          $event->cancel();
          return;
        }

      } elseif (!$player->hasPermission("redskyblock.bypass")) {

        $event->cancel();
        return;
      }
    }
  }

  public function onPlace(BlockPlaceEvent $event) {

    $plugin = $this->plugin;
    $masterWorld = $plugin->islandManager->getMasterWorld();
    $block = $event->getBlock();
    $blockWorld = $block->getPosition()->world;
    $player = $event->getPlayer();

    if ($masterWorld === $blockWorld) {

      $island = $plugin->islandManager->getIslandAtBlock($block);
      if ($island instanceof Island) {

        $members = $island->getMembers();
        $creator = $island->getCreator();
        $playerName = $player->getName();
        $playerNameLower = strtolower($playerName);

        if (array_key_exists($playerNameLower, $members) || $playerName === $creator || $player->hasPermission("redskyblock.bypass")) {

          $valuableArray = $plugin->cfg->get("Valuable Blocks", []);
          $blockID = $block->getID();
          if (array_key_exists(strval($blockID), $valuableArray)) {

            $island->addValue((int) $valuableArray[strval($blockID)]);
          }
        } else {

          $event->cancel();
          return;
        }
      } elseif (!$player->hasPermission("redskyblock.bypass")) {

        $event->cancel();
        return;
      }
    }
  }

  public function onTeleport(EntityTeleportEvent $event) {

    $entity = $event->getEntity();
    if ($entity instanceof Player) {

      $entityEndWorld = $event->getTo()->world;
      $masterWorld = $this->plugin->islandManager->getMasterWorld();
      if ($entityEndWorld !== $masterWorld && !$entity->hasPermission("redskyblock.admin")) {

        if ($entity->getAllowFlight()) {

          $entity->setAllowFlight(false);
          $entity->setFlying(false);
        }
      }
    }
  }
}
