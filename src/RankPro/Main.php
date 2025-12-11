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
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\scheduler\Task;
use pocketmine\block\VanillaBlocks;

class Main extends PluginBase implements Listener {

    private Config $players;
    private array $playtime = [];
    
    private const RANKS = [
        "Bronze" => ["color" => "§6", "xp" => 0],
        "Silver" => ["color" => "§7", "xp" => 25],
        "Gold" => ["color" => "§e", "xp" => 75],
        "Platinum" => ["color" => "§b", "xp" => 225],
        "Diamond" => ["color" => "§5", "xp" => 525],
        "Master" => ["color" => "§6", "xp" => 1425],
        "Grandmaster" => ["color" => "§6§l", "xp" => 3425],
        "Legendary" => ["color" => "§c", "xp" => 7425],
        "Mythic" => ["color" => "§c§l", "xp" => 17425]
    ];

    private const XP_PLAYTIME = 20;
    private const XP_PVP_WIN = 10;
    private const XP_PVP_STREAK_5 = 100;
    private const XP_IRON = 5;
    private const XP_DIAMOND = 15;
    private const XP_EMERALD = 20;

    protected function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        
        @mkdir($this->getDataFolder());
        $this->players = new Config($this->getDataFolder() . "players.yml", Config::YAML);
        
        $this->getScheduler()->scheduleRepeatingTask(new PlaytimeTask($this), 20 * 60);
        
        $this->getLogger()->info(TF::GREEN . "RankPro by Firekid846 enabled!");
        $this->getLogger()->info(TF::YELLOW . "XP System: Playtime, Mining, PvP");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        
        switch ($command->getName()) {
            case "xpbal":
            case "xp":
                return $this->checkXP($sender, $args);
            
            case "rankadmin":
                if (!$sender->hasPermission("rankpro.admin")) {
                    $sender->sendMessage(TF::RED . "You don't have permission!");
                    return true;
                }
                return $this->adminCommands($sender, $args);
        }

        return false;
    }

    private function checkXP(CommandSender $sender, array $args): bool {
        if (!$sender instanceof Player && count($args) < 1) {
            $sender->sendMessage(TF::YELLOW . "Usage: /xpbal [player]");
            return true;
        }

        $targetName = count($args) > 0 ? $args[0] : ($sender instanceof Player ? $sender->getName() : "");

        if (!$this->players->exists($targetName)) {
            $this->initPlayer($targetName);
        }

        $data = $this->players->get($targetName);
        $xp = (int)$data["xp"];
        $rank = $this->getRankByXP($xp);
        $nextRank = $this->getNextRank($rank);
        
        $color = self::RANKS[$rank]["color"];

        $sender->sendMessage(TF::GOLD . "━━━━━━━ XP & Rank ━━━━━━━");
        $sender->sendMessage(TF::YELLOW . "Player: " . TF::WHITE . $targetName);
        $sender->sendMessage(TF::YELLOW . "Current Rank: " . $color . $rank);
        $sender->sendMessage(TF::YELLOW . "Total XP: " . TF::AQUA . number_format($xp));
        
        if ($nextRank !== null) {
            $needed = self::RANKS[$nextRank]["xp"] - $xp;
            $nextColor = self::RANKS[$nextRank]["color"];
            $sender->sendMessage(TF::YELLOW . "Next Rank: " . $nextColor . $nextRank);
            $sender->sendMessage(TF::YELLOW . "XP Needed: " . TF::RED . number_format($needed));
        } else {
            $sender->sendMessage(TF::GREEN . "✓ MAX RANK!");
        }
        
        $sender->sendMessage(TF::GOLD . "━━━━━━━━━━━━━━━━━━━━━━━━");

        return true;
    }

    private function adminCommands(CommandSender $sender, array $args): bool {
        if (count($args) < 1) {
            $sender->sendMessage(TF::GOLD . "━━━━━━━ Rank Admin ━━━━━━━");
            $sender->sendMessage(TF::YELLOW . "/rankadmin give <p> <xp>" . TF::GRAY . " - Give XP");
            $sender->sendMessage(TF::YELLOW . "/rankadmin take <p> <xp>" . TF::GRAY . " - Take XP");
            $sender->sendMessage(TF::YELLOW . "/rankadmin set <p> <xp>" . TF::GRAY . " - Set XP");
            $sender->sendMessage(TF::YELLOW . "/rankadmin setrank <p> <rank>" . TF::GRAY . " - Force rank");
            $sender->sendMessage(TF::YELLOW . "/rankadmin reset <p>" . TF::GRAY . " - Reset to 0 XP");
            $sender->sendMessage(TF::YELLOW . "/rankadmin list" . TF::GRAY . " - Show ranks");
            $sender->sendMessage(TF::GOLD . "━━━━━━━━━━━━━━━━━━━━━━━━");
            return true;
        }

        $action = strtolower($args[0]);

        switch ($action) {
            case "give":
                if (count($args) < 3) {
                    $sender->sendMessage(TF::YELLOW . "Usage: /rankadmin give <player> <xp>");
                    return true;
                }
                $this->addXP($args[1], (int)$args[2]);
                $sender->sendMessage(TF::GREEN . "✓ Gave " . $args[2] . " XP to " . $args[1]);
                return true;

            case "take":
                if (count($args) < 3) {
                    $sender->sendMessage(TF::YELLOW . "Usage: /rankadmin take <player> <xp>");
                    return true;
                }
                $this->removeXP($args[1], (int)$args[2]);
                $sender->sendMessage(TF::GREEN . "✓ Took " . $args[2] . " XP from " . $args[1]);
                return true;

            case "set":
                if (count($args) < 3) {
                    $sender->sendMessage(TF::YELLOW . "Usage: /rankadmin set <player> <xp>");
                    return true;
                }
                $this->setXP($args[1], (int)$args[2]);
                $sender->sendMessage(TF::GREEN . "✓ Set " . $args[1] . "'s XP to " . $args[2]);
                return true;

            case "setrank":
                if (count($args) < 3) {
                    $sender->sendMessage(TF::YELLOW . "Usage: /rankadmin setrank <player> <rank>");
                    return true;
                }
                $rankName = ucfirst(strtolower($args[2]));
                if (!isset(self::RANKS[$rankName])) {
                    $sender->sendMessage(TF::RED . "Invalid rank!");
                    return true;
                }
                $this->setXP($args[1], self::RANKS[$rankName]["xp"]);
                $sender->sendMessage(TF::GREEN . "✓ Set " . $args[1] . " to rank " . self::RANKS[$rankName]["color"] . $rankName);
                return true;

            case "reset":
                if (count($args) < 2) {
                    $sender->sendMessage(TF::YELLOW . "Usage: /rankadmin reset <player>");
                    return true;
                }
                $this->setXP($args[1], 0);
                $sender->sendMessage(TF::GREEN . "✓ Reset " . $args[1] . " to Bronze (0 XP)");
                return true;

            case "list":
                $sender->sendMessage(TF::GOLD . "━━━━━━━ Ranks & XP ━━━━━━━");
                foreach (self::RANKS as $name => $data) {
                    $sender->sendMessage($data["color"] . $name . TF::GRAY . " - " . number_format($data["xp"]) . " XP");
                }
                $sender->sendMessage(TF::GOLD . "━━━━━━━━━━━━━━━━━━━━━━━━");
                return true;
        }

        return true;
    }

    public function addXP(string $player, int $amount, bool $silent = false): void {
        if (!$this->players->exists($player)) {
            $this->initPlayer($player);
        }

        $data = $this->players->get($player);
        $oldXP = (int)$data["xp"];
        $newXP = $oldXP + $amount;
        
        $data["xp"] = $newXP;
        $this->players->set($player, $data);
        $this->players->save();

        $oldRank = $this->getRankByXP($oldXP);
        $newRank = $this->getRankByXP($newXP);

        if ($oldRank !== $newRank) {
            $this->rankUp($player, $newRank);
        }

        $this->updatePlayerDisplay($player);
    }

    public function removeXP(string $player, int $amount): void {
        if (!$this->players->exists($player)) {
            $this->initPlayer($player);
        }

        $data = $this->players->get($player);
        $oldXP = (int)$data["xp"];
        $newXP = max(0, $oldXP - $amount);
        
        $data["xp"] = $newXP;
        $this->players->set($player, $data);
        $this->players->save();

        $this->updatePlayerDisplay($player);
    }

    public function setXP(string $player, int $xp): void {
        if (!$this->players->exists($player)) {
            $this->initPlayer($player);
        }

        $data = $this->players->get($player);
        $data["xp"] = $xp;
        $this->players->set($player, $data);
        $this->players->save();

        $this->updatePlayerDisplay($player);
    }

    private function rankUp(string $playerName, string $newRank): void {
        $player = $this->getServer()->getPlayerExact($playerName);
        if ($player !== null) {
            $color = self::RANKS[$newRank]["color"];
            
            $player->sendMessage(TF::GOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $player->sendMessage(TF::GREEN . "✓ RANK UP!");
            $player->sendMessage(TF::YELLOW . "New Rank: " . $color . $newRank);
            $player->sendMessage(TF::GOLD . "━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            
            foreach ($this->getServer()->getOnlinePlayers() as $p) {
                if ($p->getName() !== $playerName) {
                    $p->sendMessage($color . $playerName . TF::YELLOW . " ranked up to " . $color . $newRank . TF::YELLOW . "!");
                }
            }
        }
    }

    private function getRankByXP(int $xp): string {
        $currentRank = "Bronze";
        
        foreach (self::RANKS as $rank => $data) {
            if ($xp >= $data["xp"]) {
                $currentRank = $rank;
            } else {
                break;
            }
        }
        
        return $currentRank;
    }

    private function getNextRank(string $currentRank): ?string {
        $found = false;
        foreach (array_keys(self::RANKS) as $rank) {
            if ($found) {
                return $rank;
            }
            if ($rank === $currentRank) {
                $found = true;
            }
        }
        return null;
    }

    private function initPlayer(string $player): void {
        $this->players->set($player, [
            "xp" => 0,
            "pvp_streak" => 0
        ]);
        $this->players->save();
    }

    private function updatePlayerDisplay(string $playerName): void {
        $player = $this->getServer()->getPlayerExact($playerName);
        if ($player !== null) {
            $data = $this->players->get($playerName);
            $xp = (int)$data["xp"];
            $rank = $this->getRankByXP($xp);
            $color = self::RANKS[$rank]["color"];
            
            $player->setDisplayName($color . $playerName . TF::RESET);
        }
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();

        if (!$this->players->exists($playerName)) {
            $this->initPlayer($playerName);
        }

        $this->playtime[$playerName] = time();
        $this->updatePlayerDisplay($playerName);
    }

    public function onPlayerChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();

        if (!$this->players->exists($playerName)) {
            return;
        }

        $data = $this->players->get($playerName);
        $xp = (int)$data["xp"];
        $rank = $this->getRankByXP($xp);
        $color = self::RANKS[$rank]["color"];
        
        foreach ($this->getServer()->getOnlinePlayers() as $recipient) {
            $recipient->sendMessage($color . "[" . $rank . "] " . $color . $playerName . TF::WHITE . ": " . $event->getMessage());
        }
        $event->cancel();
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $block = $event->getBlock();

        $xp = 0;

        if ($block->isSameType(VanillaBlocks::IRON_ORE()) || $block->isSameType(VanillaBlocks::DEEPSLATE_IRON_ORE())) {
            $xp = self::XP_IRON;
        } elseif ($block->isSameType(VanillaBlocks::DIAMOND_ORE()) || $block->isSameType(VanillaBlocks::DEEPSLATE_DIAMOND_ORE())) {
            $xp = self::XP_DIAMOND;
        } elseif ($block->isSameType(VanillaBlocks::EMERALD_ORE()) || $block->isSameType(VanillaBlocks::DEEPSLATE_EMERALD_ORE())) {
            $xp = self::XP_EMERALD;
        }

        if ($xp > 0) {
            $this->addXP($player->getName(), $xp, true);
        }
    }

    public function givePlaytimeXP(): void {
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $playerName = $player->getName();
            
            if (isset($this->playtime[$playerName])) {
                $this->addXP($playerName, self::XP_PLAYTIME, true);
            }
        }
    }

    public function addPvPWin(string $player): void {
        if (!$this->players->exists($player)) {
            $this->initPlayer($player);
        }

        $data = $this->players->get($player);
        $data["pvp_streak"] = isset($data["pvp_streak"]) ? (int)$data["pvp_streak"] + 1 : 1;
        
        $this->players->set($player, $data);
        $this->players->save();

        if ($data["pvp_streak"] === 5) {
            $this->addXP($player, self::XP_PVP_STREAK_5, false);
            $p = $this->getServer()->getPlayerExact($player);
            if ($p !== null) {
                $p->sendMessage(TF::GREEN . "✓ 5 Win Streak! +" . self::XP_PVP_STREAK_5 . " XP Bonus!");
            }
        } else {
            $this->addXP($player, self::XP_PVP_WIN, false);
        }
    }

    public function resetPvPStreak(string $player): void {
        if (!$this->players->exists($player)) {
            return;
        }

        $data = $this->players->get($player);
        $data["pvp_streak"] = 0;
        $this->players->set($player, $data);
        $this->players->save();
    }

    protected function onDisable(): void {
        $this->players->save();
    }
}

class PlaytimeTask extends Task {
    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        $this->plugin->givePlaytimeXP();
    }
}
