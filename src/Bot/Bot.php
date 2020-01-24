<?php

namespace Bot;

use pocketmine\{
	Player, Server
};
use pocketmine\entity\{
	Human, Entity
};
use pocketmine\event\entity\{
	EntityDamageEvent, EntityDamageByEntityEvent
};
use pocketmine\block\{
	Slab, Stair, Flowable
};
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\block\Liquid;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat as C;

class Bot extends Human{

	const FIND_DISTANCE = 15;
	const LOSE_DISTANCE = 25;

	public $target = "";
	public $findNewTargetTicks = 0;
	public $randomPosition = null;
	public $findNewPositionTicks = 200;
	public $jumpTicks = 5;
	public $attackWait = 20;
	public $canWhistle = true;
	public $attackDamage = 4;
	public $speed = 0.35;
	public $startingHealth = 20;
	public $assisting = [];

	public function __construct(Level $level, CompoundTag $nbt){
		parent::__construct($level, $nbt);
		$this->setMaxHealth($this->startingHealth);
		$this->setHealth($this->startingHealth);
		#$this->setNametag($this->getNametag());
		$this->generateRandomPosition();
	}


	public function getType(){
		return "Bot";
	}
	public function getNametag() : string{
		return C::RED . $this->getType() . " " . C::GREEN . "(" . $this->getHealth() . "/" . $this->getMaxHealth() . ")";
	}
	public function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if(!$this->isAlive()){
			if(!$this->closed) $this->flagForDespawn();
			return false;
		}
		$this->setNametag($this->getNametag());
		if($this->hasTarget()){
			return $this->attackTarget();
		}
		if($this->findNewTargetTicks > 0){
			$this->findNewTargetTicks--;
		}
		if(!$this->hasTarget() && $this->findNewTargetTicks === 0){
			$this->findNewTarget();
		}
		if($this->jumpTicks > 0){
			$this->jumpTicks--;
		}
		if($this->findNewPositionTicks > 0){
			$this->findNewPositionTicks--;
		}
		if(!$this->isOnGround()){
			if($this->motion->y > -$this->gravity * 4){
				$this->motion->y = -$this->gravity * 4;
			}else{
				$this->motion->y += $this->isUnderwater() ? $this->gravity : -$this->gravity;
			}
		}else{
			$this->motion->y -= $this->gravity;
		}
		$this->move($this->motion->x, $this->motion->y, $this->motion->z);
		if($this->shouldJump()){
			$this->jump();
		}
		if($this->atRandomPosition() || $this->findNewPositionTicks === 0){
			$this->generateRandomPosition();
			$this->findNewPositionTicks = 200;
			return true;
		}
		$position = $this->getRandomPosition();
		$x = $position->x - $this->getX();
		$y = $position->y - $this->getY();
		$z = $position->z - $this->getZ();
		if($x * $x + $z * $z < 4 + $this->getScale()) {
			$this->motion->x = 0;
			$this->motion->z = 0;
		} else {
			$this->motion->x = $this->getSpeed() * 0.15 * ($x / (abs($x) + abs($z)));
			$this->motion->z = $this->getSpeed() * 0.15 * ($z / (abs($x) + abs($z)));
		}
		$this->yaw = rad2deg(atan2(-$x, $z));
		$this->pitch = 0;
		$this->move($this->motion->x, $this->motion->y, $this->motion->z);
		if($this->shouldJump()){
			$this->jump();
		}
		$this->updateMovement();
		return $this->isAlive();
	}
	public function attackTarget(){
		$target = $this->getTarget();
		if($target == null || $target->distance($this) >= self::LOSE_DISTANCE){
			$this->target = null;
			return true;
		}
		if($this->jumpTicks > 0) {
			$this->jumpTicks--;
		}
		if(!$this->isOnGround()) {
			if($this->motion->y > -$this->gravity * 4){
				$this->motion->y = -$this->gravity * 4;
			}else{
				$this->motion->y += $this->isUnderwater() ? $this->gravity : -$this->gravity;
			}
		}else{
			$this->motion->y -= $this->gravity;
		}
		$this->move($this->motion->x, $this->motion->y, $this->motion->z);
		if($this->shouldJump()){
			$this->jump();
		}
		$x = $target->x - $this->x;
		$y = $target->y - $this->y;
		$z = $target->z - $this->z;
		if($x * $x + $z * $z < 1.2){
			$this->motion->x = 0;
			$this->motion->z = 0;
		} else {
			$this->motion->x = $this->getSpeed() * 0.15 * ($x / (abs($x) + abs($z)));
			$this->motion->z = $this->getSpeed() * 0.15 * ($z / (abs($x) + abs($z)));
		}
		$this->yaw = rad2deg(atan2(-$x, $z));
		$this->pitch = rad2deg(-atan2($y, sqrt($x * $x + $z * $z)));
		$this->move($this->motion->x, $this->motion->y, $this->motion->z);
		if($this->shouldJump()){
			$this->jump();
		}
		if($this->distance($target) <= $this->getScale() + 0.3 && $this->attackWait <= 0){
			$event = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $this->getBaseAttackDamage());
			if($target->getHealth() - $event->getFinalDamage() <= 0){
				$event->setCancelled(true);
				$this->target = null;
				$this->findNewTarget();
			}
			$this->broadcastEntityEvent(4);
			$target->attack($event);
			$this->attackWait = 3;
		}
		$this->updateMovement();
		$this->attackWait--;
		return $this->isAlive();
	}
	public function attack(EntityDamageEvent $source) : void{
        if($source->isCancelled()){
            $source->setCancelled();
            return;
        }
		if($source instanceof EntityDamageByEntityEvent){
			$killer = $source->getDamager();
			if($killer instanceof Player){
				if($killer->isSpectator()){
					$source->setCancelled(true);
					return;
				}
				if($this->target != $killer->getName() && mt_rand(1,5) == 1 || $this->target == ""){
					$this->target = $killer->getName();
				}
				if(!isset($this->assisting[$killer->getName()])){
					$this->assisting[$killer->getName()] = true;
				}
				if($this->getHealth() <= $this->getMaxHealth() / 2 && mt_rand(0,2) == 1 && $this->canWhistle){
					$this->whistle();
					$this->canWhistle = false;
				}
			}
		}
		parent::attack($source);
	}
	public function knockBack(Entity $attacker, float $damage, float $x, float $z, float $base = 0.4) : void{
		parent::knockBack($attacker, $damage, $x, $z, $base * 2);
	}
	public function whistle(){
		foreach($this->getLevel()->getNearbyEntities($this->getBoundingBox()->expandedCopy(15, 15, 15)) as $entity){
			if($entity instanceof $this && !$entity->hasTarget() && $entity->canWhistle){
				$entity->target = $this->target;
				$entity->canWhistle = false;
			}
		}
	}
	public function kill() : void{
		parent::kill();
	}
	//Targetting//
	public function findNewTarget(){
		$distance = self::FIND_DISTANCE;
		$target = null;
		foreach($this->getLevel()->getPlayers() as $player){
			if($player->distance($this) <= $distance && !$player->isSpectator()){
				$distance = $player->distance($this);
				$target = $player;
			}
		}
		$this->findNewTargetTicks = 60;
		$this->target = ($target != null ? $target->getName() : "");
	}
	public function hasTarget(){
		$target = $this->getTarget();
		if($target == null) return false;
		$player = $this->getTarget();
		return !$player->isSpectator();
	}
	public function getTarget(){
		return Server::getInstance()->getPlayerExact((string) $this->target);
	}
	public function atRandomPosition(){
		return $this->getRandomPosition() == null || $this->distance($this->getRandomPosition()) <= 2;
	}
	public function getRandomPosition(){
		return $this->randomPosition;
	}
	public function generateRandomPosition(){
		$minX = $this->getFloorX() - 8;
		$minY = $this->getFloorY() - 8;
		$minZ = $this->getFloorZ() - 8;
		$maxX = $minX + 16;
		$maxY = $minY + 16;
		$maxZ = $minZ + 16;
		$level = $this->getLevel();
		for($attempts = 0; $attempts < 16; ++$attempts){
			$x = mt_rand($minX, $maxX);
			$y = mt_rand($minY, $maxY);
			$z = mt_rand($minZ, $maxZ);
			while($y >= 0 and !$level->getBlockAt($x, $y, $z)->isSolid()){
				$y--;
			}
			if($y < 0){
				continue;
			}
			$blockUp = $level->getBlockAt($x, $y + 1, $z);
			$blockUp2 = $level->getBlockAt($x, $y + 2, $z);
			if($blockUp->isSolid() or $blockUp instanceof Liquid or $blockUp2->isSolid() or $blockUp2 instanceof Liquid){
				continue;
			}
			break;
		}
		$this->randomPosition = new Vector3($x, $y + 1, $z);
	}
	public function getSpeed(){
		return ($this->isUnderwater() ? $this->speed / 2 : $this->speed);
	}
	public function getBaseAttackDamage(){
		return $this->attackDamage;
	}
	public function getAssisting(){
		$assisting = [];
		foreach($this->assisting as $name => $bool){
			$player = Server::getInstance()->getPlayerExact($name);
			if($player instanceof Player) $assisting[] = $player;
		}
		return $assisting;
	}
	public function getFrontBlock($y = 0){
		$dv = $this->getDirectionVector();
		$pos = $this->asVector3()->add($dv->x * $this->getScale(), $y + 1, $dv->z * $this->getScale())->round();
		return $this->getLevel()->getBlock($pos);
	}
	public function shouldJump(){
		if($this->jumpTicks > 0) return false;
		return $this->isCollidedHorizontally || 
		($this->getFrontBlock()->getId() != 0 || $this->getFrontBlock(-1) instanceof Stair) ||
		($this->getLevel()->getBlock($this->asVector3()->add(0,-0,5)) instanceof Slab &&
		(!$this->getFrontBlock(-0.5) instanceof Slab && $this->getFrontBlock(-0.5)->getId() != 0)) &&
		$this->getFrontBlock(1)->getId() == 0 && 
		$this->getFrontBlock(2)->getId() == 0 && 
		!$this->getFrontBlock() instanceof Flowable &&
		$this->jumpTicks == 0;
	}
	public function getJumpMultiplier(){
		return 16;
		if(
			$this->getFrontBlock() instanceof Slab ||
			$this->getFrontBlock() instanceof Stair ||
			$this->getLevel()->getBlock($this->asVector3()->subtract(0,0.5)->round()) instanceof Slab &&
			$this->getFrontBlock()->getId() != 0
		){
			$fb = $this->getFrontBlock();
			if($fb instanceof Slab && $fb->getDamage() & 0x08 > 0) return 8;
			if($fb instanceof Stair && $fb->getDamage() & 0x04 > 0) return 8;
			return 4;
		}
		return 8;
	}
	public function jump() : void{
		$this->motion->y = $this->gravity * $this->getJumpMultiplier();
		$this->move($this->motion->x * 1.25, $this->motion->y, $this->motion->z * 1.25);
		$this->jumpTicks = 5; //($this->getJumpMultiplier() == 4 ? 2 : 5);
	}
}