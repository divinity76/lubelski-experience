<?php
declare(strict_types = 1);

class MonitorCronjob
{

    protected const DB_FILE = __DIR__ . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "database.db3";

    /** @var \PDO $db */
    public $db;

    public function dbquote($val): string
    {
        if (is_null($val)) {
            return "NULL";
        }
        if (! is_scalar($val)) {
            throw new \InvalidArgumentException("only accept null and scalar values!");
        }
        if (is_bool($val)) {
            return ($val ? "1" : "0");
        }
        if (is_int($val) || is_float($val)) {
            // todo: consider NaN and stuff..?
            return ((string) $val);
        }
        if (is_string($val)) {
            return $this->db->quote($val);
        }
        throw new \LogicException("unknown scalar type!?");
    }

    function __construct()
    {
        if (! is_writable($this::DB_FILE)) {
            if (! file_exists($this::DB_FILE)) {
                $this->createDB($this::DB_FILE);
            }
            throw new \RuntimeException("db file unwritable: " . $this::DB_FILE);
        }
        $this->db = new \PDO("sqlite:" . $this::DB_FILE, '', '', array(
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ));
    }

    protected function createDB(): void
    {
        $db_schema_file = __DIR__ . DIRECTORY_SEPARATOR . "database" . DIRECTORY_SEPARATOR . "schema.sql";
        $db = new \PDO("sqlite:" . $this::DB_FILE, '', '', array(
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ));
        $schema = file_get_contents($db_schema_file);
        $db->exec($schema);
    }

    public static function getOnlineData(): array
    {
        $ret = [];
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => 'http://lubelski-classic.com/?highscores',
            CURLOPT_ENCODING => '',
            CURLOPT_RETURNTRANSFER => 1
        ));
        $html = curl_exec($ch);
        curl_close($ch);
        if (empty($html)) {
            throw new \LogicException("curl_exec failed!? todo better error logging");
        }
        $domd = new DOMDocument();
        @$domd->loadHTML($html);
        $xp = new DOMXPath($domd);
        $table = $xp->query("//table[contains(.,'Rank')]");
        if (count($table) !== 2) {
            throw new \LogicException("layout changed!?");
        }
        $table = $table->item(1);
        $players = $xp->query("./tr", $table);
        /** @var DOMNodeList $players */
        foreach ($players as $index => $player) {
            /** @var DOMElement $player */
            if ($index < 2) {
                // the first 2 are not players
                continue;
            }
            $data = [];
            $tds = $player->getElementsByTagName("td");
            if (0) {
                foreach ($tds as $in => $td) {
                    var_dump($in, $td->textContent);
                }
                continue;
            }
            if (count($tds) !== 6) {
                throw new \LogicException("layout changed!?");
            }
            {
                $nameAndVocationAndOnlinestatus = $tds->item(3);
                $nameAndOnlinestatus = $nameAndVocationAndOnlinestatus->getElementsByTagName("span")->item(0);
                $name = trim($nameAndOnlinestatus->textContent);
                $data["name"] = $name;
                $isOnline = (false !== stripos($nameAndOnlinestatus->getAttribute("style"), 'green'));
                $data["online"] = $isOnline;
                $vocation = trim($nameAndVocationAndOnlinestatus->getElementsByTagName("small")->item(0)->textContent);
                $data["vocation"] = $vocation;
            }
            {
                $exp = $tds->item(5)->textContent;
                $exp = trim(str_replace([
                    " ",
                    ","
                ], "", $exp));
                $exp = filter_var($exp, FILTER_VALIDATE_INT, array(
                    'options' => array(
                        'min_val' => 0
                    )
                ));
                if (! is_int($exp)) {
                    throw new \LogicException("failed to extract exp!");
                }
                $data["experience"] = $exp;
            }
            {
                $level = $tds->item(4)->textContent;
                $level = trim($level);
                $level = filter_var($level, FILTER_VALIDATE_INT, array(
                    'options' => array(
                        'min_val' => 0
                    )
                ));
                if (! is_int($level)) {
                    throw new \LogicException("failed to extract level!");
                }
                $data["level"] = $level;
            }
            {
                $level_rank = $tds->item(1)->textContent;
                $level_rank = trim(str_replace([
                    ",",
                    "."
                ], "", $level_rank));
                $level_rank = filter_var($level_rank, FILTER_VALIDATE_INT, array(
                    'options' => array(
                        'min_val' => 0
                    )
                ));
                if (! is_int($level_rank)) {
                    throw new \LogicException("failed to extract level_rank!");
                }
                $data["level_rank"] = $level_rank;
            }
            $ret[] = $data;
        }
        return $ret;
    }

    public function getPlayerIdByName(string $name, bool $createIfMissing = true, bool &$existed = null): ?int
    {
        $res = $this->db->query("SELECT id FROM players WHERE `name` = " . $this->dbquote($name))
            ->fetch();
        if (! empty($res)) {
            $existed = true;
            return ((int) $res["id"]);
        }
        $existed = false;
        if (! $createIfMissing) {
            return null;
        }
        $this->db->exec('INSERT INTO `players` (name,notes) VALUES(' . $this->dbquote($name) . "," . $this->dbquote("") . ');');
        return ((int) $this->db->lastInsertId());
    }

    public function updateExperienceTable(): void
    {
        $onlineData = $this->getOnlineData();
        $newPlayers = 0;
        foreach ($onlineData as $player) {
            // $player sample:
            array(
                'name' => 'Nassir Sorc',
                'online' => false,
                'vocation' => 'Sorcerer',
                'experience' => 4200,
                'level' => 8,
                'level_rank' => 100
            );
            // db record sample:
            array(
                'id' => '1',
                'timestamp' => '0',
                'player_id' => '1',
                'level_rank' => '123456789',
                'online' => '1',
                'level' => '1',
                'experience' => '123'
            );
            $player["experience"] = 123;
            $existed = null;
            $player["id"] = $this->getPlayerIdByName($player["name"], true, $existed);
            $insert = array(
                ":timestamp" => time(),
                ":player_id" => $player["id"],
                ":level_rank" => $player["level_rank"],
                ":online" => (int) $player["online"],
                ":level" => $player["level"],
                ":experience" => $player["experience"]
            );
            $sql = "INSERT INTO `experience` (timestamp,player_id,level_rank,online,level,experience) VALUES(:timestamp,:player_id,:level_rank,:online,:level,:experience);";
            $stm = $this->db->prepare($sql);
            $stm->execute($insert);
        }
    }
}

$monitor = new MonitorCronjob();
// 5 minutes
$updateSleep = 5 * 60;

for (;;) {
    $lastInsert = (int) $monitor->db->query("SELECT MAX(timestamp) FROM experience LIMIT 1")->fetch(\PDO::FETCH_NUM)[0];
    // $lastInsert = time();
    $timeToSleep = ($lastInsert + $updateSleep) - time();
    if ($timeToSleep > 0) {
        echo "sleeping {$timeToSleep} seconds and checking again..\n";
        @sleep($timeToSleep);
        continue;
    }
    $monitor->updateExperienceTable();
}