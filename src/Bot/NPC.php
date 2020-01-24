<?php
namespace Bot;

use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;

class NPC extends Bot{

	public $attackDamage = 10;
	public $speed = 1.00;
	public $startingHealth = 20;

	public function __construct(Level $level, CompoundTag $nbt){
		parent::__construct($level, $nbt);
	}

	public function getType(){
		return "Baeeee";
	}
}