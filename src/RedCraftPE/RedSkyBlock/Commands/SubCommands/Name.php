<?php

namespace RedCraftPE\RedSkyBlock\Commands\SubCommands;

use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

use RedCraftPE\RedSkyBlock\Commands\SBSubCommand;
use RedCraftPE\RedSkyBlock\Island;

class Name extends SBSubCommand {

  public function prepare(): void {

    $this->setPermission("redskyblock.island");
  }

  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {

    $island = $this->plugin->islandManager->getIslandAtPlayer($sender);
    if ($island instanceof Island) {

      $islandName = $island->getName();

      $message = $this->getMShop()->construct("ISLAND_NAME_OTHER");
      $message = str_replace("{ISLAND_NAME}", $islandName, $message);
      $sender->sendMessage($message);
    } else {

      if ($this->checkIsland($sender)) {

        $island = $this->plugin->islandManager->getIsland($sender);
        $islandName = $island->getName();

        $message = $this->getMShop()->construct("ISLAND_NAME_SELF");
        $message = str_replace("{ISLAND_NAME}", $islandName, $message);
        $sender->sendMessage($message);
      } else {

        $message = $this->getMShop()->construct("NO_ISLAND");
        $sender->sendMessage($message);
      }
    }
  }
}
