<?php

declare(strict_types=1);

namespace ARTulloss\FloatingHotbar;

use ARTulloss\Hotbar\Events\LoseHotbarEvent;
use ARTulloss\Hotbar\Main as HBMain;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\item\ItemIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use function lcg_value;

/**
 *  _  _  __ _____ __  __  ___
 * | || |/__\_   _|  \/  \| _ \
 * | >< | \/ || | | -< /\ | v /
 * |_||_|\__/ |_| |__/_||_|_|_\
 *
 * @author ARTulloss
 * @link https://github.com/artulloss
 */

class Main extends PluginBase implements Listener{

    private $hotbar;
    /** @var ItemEntity[] */
    private array $playerItems;
    private float $heightOffset;
    private bool $movementTracking;

	public function onEnable(): void{
	    $this->getServer()->getPluginManager()->registerEvents($this, $this);

        /** @var HBMain $hotbar */
        $this->hotbar = $this->getServer()->getPluginManager()->getPlugin('Hotbar');
        $config = $this->getConfig();
        $this->movementTracking = $config->get('Movement Tracking');
        $this->heightOffset = $config->get('Height Offset');
    }

    /**
     * @param PlayerMoveEvent $event
     * @priority LOWEST
     */
    public function onMove(PlayerMoveEvent $event): void{
        if($this->movementTracking) {
            $player = $event->getPlayer();
            $name = $player->getName();
            $hotbar = $this->hotbar->getHotbarUsers()?->getHotbarFor($player);
            if($hotbar !== null) {
                if(isset($this->playerItems[$name])) {
                    $position = $this->calculateRelativePosition($player);
                    $this->playerItems[$name]->teleport($position);
                }
            }
        }
    }

    /**
     * @param PlayerItemHeldEvent $event
     * @priority HIGHEST
     */
    public function holdItem(PlayerItemHeldEvent $event): void {
        $player = $event->getPlayer();
        $users = $this->hotbar->getHotbarUsers();
        $hotbarUser = $users->getHotbarFor($player);
        if($hotbarUser !== null) {
            $inv = $player->getInventory();
            $index = $inv->getHeldItemIndex();
            $items = $hotbarUser->getHotbar()->getItems();
            $item = $inv->getItem($index);
            if(isset($items[$index + 1]) && ($hotbarItem = $items[$index + 1]) && $item->getName() === $hotbarItem->getName()
                && $item->getId() === $hotbarItem->getId() && $item->getMeta() === $hotbarItem->getMeta()) {
                $name = $player->getName();
                if (isset($this->playerItems[$name])) {
                    $this->safeDespawn($this->playerItems[$name]);
                    unset($this->playerItems[$name]);
                }
                $item = $event->getItem();
                $position = $this->calculateRelativePosition($player);
                if ($item->getId() !== ItemIds::AIR) {
                    $nbt = CompoundTag::create()
                        ->setShort("Health", 5)
                        ->setShort("PickupDelay", 999);
                    // No need for Custom class.
                    $itemEntity = new ItemEntity(Location::fromObject($position, $player->getWorld(), lcg_value() * 360, 0), $item, $nbt);
                    $itemEntity->spawnTo($player);
                    $itemEntity->setHasGravity(false);
                    $this->playerItems[$name] = $itemEntity;
                }
            } elseif($item->getId() !== ItemIds::AIR)
                $users->remove($player, false);
        }
    }

    /**
     * @param Player $player
     * @return Vector3
     */
    private function calculateRelativePosition(Player $player): Vector3{
        $position = $player->getPosition()->asVector3();
        $direction = $player->getDirectionVector();
        $subtract = $direction->multiply(0.75);
        $position = $position->add($subtract->getX(), $subtract->getY(), $subtract->getZ());
        $position->y += ($player->getEyeHeight() + $this->heightOffset);
        return $position;
    }

    /**
     * @param LoseHotbarEvent $event
     */
    public function onLoseHotbar(LoseHotbarEvent $event): void{
        $name = $event->getHotbarUser()->getPlayer()->getName();
        if(isset($this->playerItems[$name]))
            $this->safeDespawn($this->playerItems[$name]);
    }

    /**
     * @param ItemEntity $item
     */
    public function safeDespawn(ItemEntity $item): void{
        if (!$item->isClosed())
            $item->flagForDespawn();
    }
}
