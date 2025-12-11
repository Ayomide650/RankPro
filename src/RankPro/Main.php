<?php

declare(strict_types=1);

namespace RankPro;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;

class Main extends PluginBase implements Listener {

    private Config $ranks;
    
    private const RANKS = [
        "Bronze" => "§6",        // Brown/Dark Orange
        "Silver" => "§7",        // Gray
        "Gold" => "§e",          // Yellow
        "Platinum" => "§b",      // Blue/Aqua
        "Diamond" => "§5",       // Purple
        "Master" => "§6",        // Gold (same as bronze but different level)
        "Grandmaster" => "§6§l", // Bold Gold
        "Legendary" => "§c",     // Red
        "Mythic" => "§c§l"       // Bold Red (Mythical)
    ];

    protected function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        
        @mkdir($this->getDataFolder());
        $this->ranks = new Config($this->getDataFolder() . "ranks.yml", Config::YAML);
        
        $this->getLogger()->info(TF::GREEN . "RankPro by Firekid846 enabled!");
        $this->getLogger()->info(TF::YELLOW . "Available ranks: Bronze, Silver, Gold, Platinum, Diamond, Master, Grandmaster, Legendary, Mythic");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        
        if ($command->getName() !== "rank") {
            return false;
        }

        if (!$sender->hasPermission("rankpro.admin")) {
            $sender->sendMessage(TF::RED . "You don't have permission!");
            return true;
        }

        if (count($args) < 1) {
            $this->sendHelp($sender);
            return true;
        }

        $action = strtolower($args[0]);

        switch ($action) {
            case "set":
                return $this->setRank($sender, $args);
            case "remove":
                return $this->removeRank($sender, $args);
            case "list":
                return $this->listRanks($sender);
            case "check":
                return $this->checkRank($sender, $args);
            default:
                $this->sendHelp($sender);
                return true;
        }
    }

    private function setRank(CommandSender $sender, array $args): bool {
        if (count($args) < 3) {
            $sender->sendMessage(TF::YELLOW . "Usage: /rank set <player> <rank>");
            $sender->sendMessage(TF::GRAY . "Available ranks: Bronze, Silver, Gold, Platinum, Diamond, Master, Grandmaster, Legendary, Mythic");
            return true;
        }

        $playerName = $args[1];
        $rankName = ucfirst(strtolower($args[2]));

        if (!isset(self::RANKS[$rankName])) {
            $sender->sendMessage(TF::RED . "Invalid rank! Available ranks:");
            foreach (array_keys(self::RANKS) as $rank) {
                $sender->sendMessage(TF::YELLOW . "  • " . $rank);
            }
            return true;
        }

        $this->ranks->set($playerName, $rankName);
        $this->ranks->save();

        $color = self::RANKS[$rankName];
        $sender->sendMessage(TF::GREEN . "✓ Set " . $playerName . "'s rank to " . $color . $rankName);

        $target = $this->getServer()->getPlayerExact($playerName);
        if ($target !== null) {
            $target->setDisplayName($color . $target->getName() . TF::RESET);
            $target->sendMessage(TF::GREEN . "✓ Your rank has been set to " . $color . $rankName);
        }

        return true;
    }

    private function removeRank(CommandSender $sender, array $args): bool {
        if (count($args) < 2) {
            $sender->sendMessage(TF::YELLOW . "Usage: /rank remove <player>");
            return true;
        }

        $playerName = $args[1];

        if (!$this->ranks->exists($playerName)) {
            $sender->sendMessage(TF::RED . "That player doesn't have a rank!");
            return true;
        }

        $this->ranks->remove($playerName);
        $this->ranks->save();

        $sender->sendMessage(TF::GREEN . "✓ Removed " . $playerName . "'s rank!");

        $target = $this->getServer()->getPlayerExact($playerName);
        if ($target !== null) {
            $target->setDisplayName($target->getName());
            $target->sendMessage(TF::YELLOW . "Your rank has been removed.");
        }

        return true;
    }

    private function listRanks(CommandSender $sender): bool {
        $sender->sendMessage(TF::GOLD . "━━━━━━━ Available Ranks ━━━━━━━");
        
        foreach (self::RANKS as $rankName => $color) {
            $sender->sendMessage($color . "• " . $rankName . TF::RESET . TF::GRAY . " (" . $color . "Color Preview" . TF::GRAY . ")");
        }
        
        $sender->sendMessage(TF::GOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $sender->sendMessage(TF::GRAY . "Use /rank set <player> <rank> to set ranks");

        return true;
    }

    private function checkRank(CommandSender $sender, array $args): bool {
        if (count($args) < 2) {
            if ($sender instanceof Player) {
                $playerName = $sender->getName();
            } else {
                $sender->sendMessage(TF::YELLOW . "Usage: /rank check <player>");
                return true;
            }
        } else {
            $playerName = $args[1];
        }

        if (!$this->ranks->exists($playerName)) {
            $sender->sendMessage(TF::YELLOW . $playerName . " doesn't have a rank.");
            return true;
        }

        $rankName = $this->ranks->get($playerName);
        $color = self::RANKS[$rankName];

        $sender->sendMessage(TF::GOLD . "━━━━━━━━━━━━━━━━━━━━━━");
        $sender->sendMessage(TF::YELLOW . "Player: " . TF::WHITE . $playerName);
        $sender->sendMessage(TF::YELLOW . "Rank: " . $color . $rankName);
        $sender->sendMessage(TF::GOLD . "━━━━━━━━━━━━━━━━━━━━━━");

        return true;
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();

        if ($this->ranks->exists($playerName)) {
            $rankName = $this->ranks->get($playerName);
            $color = self::RANKS[$rankName];
            $player->setDisplayName($color . $playerName . TF::RESET);
        }
    }

    public function onPlayerChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();

        if ($this->ranks->exists($playerName)) {
            $rankName = $this->ranks->get($playerName);
            $color = self::RANKS[$rankName];
            
            $format = $color . "[" . $rankName . "] " . $color . $playerName . TF::WHITE . ": %s";
            $event->setMessage(sprintf($format, $event->getMessage()));
            
            foreach ($this->getServer()->getOnlinePlayers() as $recipient) {
                $recipient->sendMessage($color . "[" . $rankName . "] " . $color . $playerName . TF::WHITE . ": " . $event->getMessage());
            }
            $event->cancel();
        }
    }

    private function sendHelp(CommandSender $sender): void {
        $sender->sendMessage(TF::GOLD . "━━━━━━━ Rank Commands ━━━━━━━");
        $sender->sendMessage(TF::YELLOW . "/rank set <p> <rank>" . TF::GRAY . " - Set player rank");
        $sender->sendMessage(TF::YELLOW . "/rank remove <p>" . TF::GRAY . " - Remove rank");
        $sender->sendMessage(TF::YELLOW . "/rank list" . TF::GRAY . " - Show all ranks");
        $sender->sendMessage(TF::YELLOW . "/rank check [p]" . TF::GRAY . " - Check rank");
        $sender->sendMessage(TF::GOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━");
    }

    protected function onDisable(): void {
        $this->ranks->save();
    }
}
