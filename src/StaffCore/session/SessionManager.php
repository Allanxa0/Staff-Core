<?php

declare(strict_types=1);

namespace StaffCore\session;

use pocketmine\player\Player;

class SessionManager {

    /** @var array<string, PlayerSession> */
    private array $sessions = [];

    public function createSession(Player $player): void {
        $this->sessions[strtolower($player->getName())] = new PlayerSession($player);
    }

    public function getSession(Player $player): ?PlayerSession {
        return $this->sessions[strtolower($player->getName())] ?? null;
    }

    public function removeSession(Player $player): void {
        unset($this->sessions[strtolower($player->getName())]);
    }
}
