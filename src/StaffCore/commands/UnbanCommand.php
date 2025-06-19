<?php

declare(strict_types=1);

namespace StaffCore\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use StaffCore\form\UnbanForm;
use StaffCore\Main;

class UnbanCommand extends Command {
    
    public function __construct() {
        parent::__construct("unban", "Desbanea a un jugador.", "/unban", []);
        $this->setPermission("staffcore.command.unban");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$this->testPermission($sender)) return false;

        if (!$sender instanceof Player) {
            $sender->sendMessage("§cEste comando solo puede ser usado en el juego.");
            return false;
        }

        $sender->sendForm(new UnbanForm($sender));
        return true;
    }
}
