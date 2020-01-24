<?php

namespace Bot;

use pocketmine\plugin\PluginBase;
use pocketmine\command\{Command, CommandSender};
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;

class Main extends PluginBase implements Listener{

	public function onEnable(): void{
		Entity::registerEntity(NPC::class, true);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool{
		if($cmd->getName() == "npc"){
			if(count($args) < 1){
				$sender->sendMessage("You forgot name niggeeeum");
				return false;
			}
			$name = implode(" ", $args);
			$nbt = Entity::createBaseNBT($sender, null, 2, 2);
			$nbt->setTag($sender->namedtag->getTag("Skin"));
			$npc = new NPC($sender->getLevel(), $nbt);
			$npc->setNameTag($name . " Health: " . round($npc->getHealth()));
			$npc->setNameTagAlwaysVisible(true);
			$npc->spawnToAll();
			$sender->sendMessage("$name bot");
		}
		return true;
	}

	public function onMove(PlayerMoveEvent $e): void{
		$player = $e->getPlayer();
		$entities = $player->getLevel()->getEntities();
		if($entities instanceof NPC){
			$player->sendMessage("D");
		}
	}
}