<?php

declare(strict_types=1);

namespace StaffCore\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use StaffCore\Main;

class StaffChatCommand extends Command {

    private Main $plugin;

    public function __construct() {
        parent::__construct("sc", "Envía un mensaje privado al chat de staff.", "/sc <mensaje>", ["staffchat"]);
        $this->setPermission("staffcore.command.staffchat");
        $this->plugin = Main::getInstance();
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$this->testPermission($sender)) return false;

        if (empty($args)) {
            $sender->sendMessage($this->plugin->getMessage("staff_chat_usage"));
            return false;
        }

        $message = implode(" ", $args);
        $formattedMessage = $this->plugin->getMessage("staff_chat.format", [
            "player" => $sender->getName(),
            "message" => $message
        ]);

        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            if ($player->hasPermission("staffcore.command.staffchat")) {
                $player->sendMessage($formattedMessage);
            }
        }
        
        return true;
    }
}
