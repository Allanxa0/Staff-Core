<?php

declare(strict_types=1);

namespace StaffCore\tasks;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use StaffCore\Main;

class VanishTask extends Task {

    public function onRun(): void {
        $sessions = Main::getInstance()->getSessionManager();
        $server = Server::getInstance();
        $vanishedPlayers = [];

        // Primero, obtenemos una lista de todos los jugadores en vanish.
        foreach ($server->getOnlinePlayers() as $player) {
            $session = $sessions->getSession($player);
            if ($session !== null && $session->isVanished()) {
                $vanishedPlayers[] = $player;
            }
        }
        
        if (empty($vanishedPlayers)) {
            // Si no hay nadie en vanish, nos aseguramos de que todos vean a todos y terminamos.
            foreach($server->getOnlinePlayers() as $player){
                foreach($server->getOnlinePlayers() as $other){
                    $player->showPlayer($other);
                }
            }
            return;
        }

        // Ahora, recorremos a todos los jugadores una sola vez.
        foreach ($server->getOnlinePlayers() as $viewer) {
            // Si el jugador no tiene permiso para ver a otros en vanish...
            if (!$viewer->hasPermission("staffcore.feature.vanish")) {
                // ...ocultamos a todos los de la lista de vanished.
                foreach ($vanishedPlayers as $vanishedPlayer) {
                    if ($viewer !== $vanishedPlayer) {
                        $viewer->hidePlayer($vanishedPlayer);
                    }
                }
            } else { // Si el jugador SÍ tiene permiso...
                // ...nos aseguramos de que pueda ver a todos.
                foreach ($vanishedPlayers as $vanishedPlayer) {
                    $viewer->showPlayer($vanishedPlayer);
                }
            }
        }
    }
}
