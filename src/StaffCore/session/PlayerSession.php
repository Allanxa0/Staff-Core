<?php

declare(strict_types=1);

namespace StaffCore\session;

use pocketmine\player\GameMode;
use pocketmine\player\Player;
use StaffCore\Main;

class PlayerSession {

    private bool $inStaffMode = false;
    private bool $isFrozen = false;
    private bool $isVanished = false;
    
    private ?string $originalNameTag = null;
    private ?GameMode $originalGamemode = null;
    private ?string $preFreezeNameTag = null;

    public function __construct(private Player $player) {
        $this->loadPersistentData();
    }

    private function loadPersistentData(): void {
        $data = Main::getInstance()->getDatabaseManager()->getPlayerData($this->player->getName());
        if ($data) {
            $this->inStaffMode = (bool)$data['is_in_staff_mode'];
            $this->isFrozen = (bool)$data['is_frozen'];
            $this->isVanished = (bool)$data['is_vanished'];
            $this->preFreezeNameTag = $data['pre_freeze_nametag'];
        }
    }

    public function getPlayer(): Player {
        return $this->player;
    }

    public function isInStaffMode(): bool {
        return $this->inStaffMode;
    }

    public function setInStaffMode(bool $status): void {
        $this->inStaffMode = $status;
        Main::getInstance()->getDatabaseManager()->setPlayerState($this->player->getName(), 'is_in_staff_mode', $status);
    }

    public function isFrozen(): bool {
        return $this->isFrozen;
    }

    public function setFrozen(bool $status): void {
        $this->isFrozen = $status;
        Main::getInstance()->getDatabaseManager()->setPlayerState($this->player->getName(), 'is_frozen', $status);
    }
    
    public function isVanished(): bool {
        return $this->isVanished;
    }

    public function setVanished(bool $status): void {
        $this->isVanished = $status;
        Main::getInstance()->getDatabaseManager()->setPlayerState($this->player->getName(), 'is_vanished', $status);
        $this->updateVanishVisibility();
    }
    
    public function getOriginalNameTag(): string {
        return $this->originalNameTag ?? $this->player->getNameTag();
    }

    public function setOriginalNameTag(string $tag): void {
        $this->originalNameTag = $tag;
    }
    
    public function getOriginalGamemode(): GameMode {
        return $this->originalGamemode ?? $this->player->getServer()->getGamemode();
    }

    public function setOriginalGamemode(GameMode $gamemode): void {
        $this->originalGamemode = $gamemode;
    }

    public function getPreFreezeNameTag(): ?string {
        return $this->preFreezeNameTag;
    }

    public function setPreFreezeNameTag(?string $tag): void {
        $this->preFreezeNameTag = $tag;
        Main::getInstance()->getDatabaseManager()->setPlayerState($this->player->getName(), 'pre_freeze_nametag', $tag);
    }

    public function saveInventory(): void {
        Main::getInstance()->getDatabaseManager()->saveInventory($this->player);
    }
    
    public function restoreInventory(): void {
        Main::getInstance()->getDatabaseManager()->restoreInventory($this->player);
    }
    
    /**
     * Actualiza la visibilidad del jugador para los demás, basado en su estado de vanish.
     * Este es el núcleo del nuevo sistema de vanish sin tareas repetitivas.
     */
    public function updateVanishVisibility(): void {
        $server = $this->player->getServer();
        foreach($server->getOnlinePlayers() as $viewer) {
            if ($this->isVanished()) {
                if (!$viewer->hasPermission("staffcore.feature.vanish.see")) {
                    $viewer->hidePlayer($this->player);
                }
            } else {
                $viewer->showPlayer($this->player);
            }
        }
    }
}
