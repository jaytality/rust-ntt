<?php

/**
 * Rust Wipe Script
 * ----------------
 *
 * Designed for the Snack Pack Gaming community
 * this should run every UTC: Thursday 1900 hrs
 *
 * FOR SERVER: No Tech Tree
 *
 * Required Rust plugins:
 *      - CustomChatCommands (https://umod.org/plugins/custom-chat-commands)
 *      - Welcomer (https://umod.org/plugins/welcomer)
 *
 * @author Johnathan Tiong <johnathan.tiong@gmail.com>
 * @copyright 2023 (c) Johnathan Tiong
 *
 **/

include "config.php";

$mysqli = mysqli_init();
$mysqli->ssl_set(NULL, NULL, "/etc/ssl/certs/ca-certificates.crt", NULL, NULL);
if(!$mysqli->real_connect(getCfg("database.host"), getCfg("database.user"), getCfg("database.pass"), getCfg("database.name"))) {
    die("Connection Error (" . mysqli_connect_error() . ") " . mysqli_connect_error());
}

$execStart   = microtime(true);
define('ROOT', dirname(__FILE__));

/**
 * send a discord message either to the public annnouncement channel or to the maintenance channel
 *
 * @param string $msg
 * @param boolean $isPublic
 * @return void
 */
function sendDiscordMessage($msg, $isPublic = false)
{
    if ($isPublic) {
        $url = getCfg('discord.announce');
    } else {
        $url = getCfg('discord.webhook');
    }

    $headers = [ 'Content-Type: application/json; charset=utf-8' ];
    $POST = [ 'username' => getCfg('discord.username'), 'content' => $msg ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($POST));
    $response   = curl_exec($ch);
}

/**
 * isDaylightSavings
 *
 * returns whether or not the server is currently in AEST or not
 * specifically for Australia/Sydney
 *
 * @param string $timezoneStr
 * @return boolean
 */
function isDaylightSavings($timezoneStr = 'Australia/Sydney') {
    $date = new DateTime('now', new DateTimeZone($timezoneStr));
    return $date->format('I') ? 'AEDT' : 'AEST';
}

/**
 * generateWipeMessage
 * creates a custom chat command "/wipe" which will tell players when the next wipe is
 * automatically. Uses CustomChatCommands plugin
 *
 * this also generates a welcomer message which will display the next wipe date as well
 *
 * @param string $scope
 * @return void
 */
function generateWipeMessage()
{
    $forceText = "";

    // build initial expected data settings for CCC
    $data = [
        'Reset Cooldowns On New Map' => true,
        'Reset Max Uses On New Map'  => true,
        'Reset Max Uses At Midnight' => true,
        'Commands' => [],
    ];

    // wipe command settings for CCC
    $wipeCommand = [
        'Command'     => 'wipe',
        'Messages'    => [],
        'Permissions' => '',
        'ConsoleCmd'  => null,
        'UserID'      => 0,
        'Broadcast'   => true,
        'RconCmd'     => null,
        'Cooldown'    => 0,
        'MaxUses'     => 0
    ];

    // help command for CCC
    $helpCommand = [
        'Command'     => 'help',
        'Messages'    => [
            "Use <color=#EC9706>/wipe</color> to view Wipe Information\r\nUse <color=#EC9706>/players</color> to view Player Information\r\nUse <color=#EC9706>/help</color> to see these commands again",
        ],
        'Permissions' => '',
        'ConsoleCmd'  => null,
        'UserID'      => 0,
        'Broadcast'   => true,
        'RconCmd'     => null,
        'Cooldown'    => 0,
        'MaxUses'     => 0
    ];

    // welcomer plugin array
    $welcomer = [
        "WelcomeMessage" => "",
        "JoinMessage" => "Player {0} has joined the server",
        "JoinMessageUnknown" => "Player {0} has joined the server",
        "LeaveMessage" => "Player {0} has left the server. Reason {1}"
    ];

    // calculate next Wipe Date
    $today = new DateTime();
    $nextMonth = $today->modify('+1 month');
    $nextMonth->setDate($nextMonth->format('Y'), $nextMonth->format('m'), 1);

    while ($nextMonth->format('N') != 5) {
        $nextMonth->modify('+1 day');
    }

    $wipeDate = $nextMonth->format('l, jS \of F');

    $wipeText = "<color=#80D000>NEXT WIPE</color> " . $wipeDate . " at 12:00pm - " . getDaylightSaving();
    $wipeCommand['Messages'][] = $wipeText;

    // adding commands to CCC
    $data['Commands'][] = $wipeCommand;
    $data['Commands'][] = $helpCommand;

    // insert into CustomChatCommands plugin
    file_put_contents(getCfg('plugins.commands'), json_encode($data));

    // insert into Welcomer Plugin
    $welcomer['WelcomeMessage'] = "Welcome to the server!\r\nThere're currently {0} players online\r\nUse <color=#EC9706>/wipe</color> to view Wipe Information\r\nUse <color=#EC9706>/players</color> to view Player Information\r\nUse <color=#EC9706>/help</color> to see what commands are available\r\n\r\n" . $wipeText;
    file_put_contents(getCfg('plugins.welcomer'), json_encode($welcomer));
}

/**
 * WIPE SERVER FUNCTIONALITY
 *
 * Below is the direct execution of server wipe functionality; this might be different based on the server (3x, NTT, Vanilla)
 * 3x - weekly 3x gathering
 * NTT - No Tech Tree (monthly)
 * Vanilla - Monthly
 *
 * THIS SERVER IS: NTT
 */

// stop the rust server
shell_exec('cd /home/rust/server && ./rustserver stop');
sendDiscordMessage($getCfg('rust.server') . ' Server Stopped');

// update the rust server + oxide + plugins
shell_exec('cd /home/rust/server && ./rustserver update');
sendDiscordMessage($getCfg('rust.server') . ' Server Updated');

shell_exec('cd /home/rust/server && ./rustserver mods-update');
sendDiscordMessage($getCfg('rust.server') . ' Oxide Plugin Manager Updated');

sendDiscordMessage('Updating all plugins');
shell_exec('cd /home/rust/server/serverfiles/oxide/plugins && ./UpdatePlugins.sh');
sendDiscordMessage('All Plugins Updated on the Server');

$fp       = fopen($getCfg('rust.config'), 'a');       // updating the rust server config
$wipedate = date('d/m');              // set date of wipe in server title to today
$mapseed  = rand(1, 2147483647);      // randomised map seed
$mapsize  = rand(4200, 4800);         // randomised world size
fwrite($fp, 'worldsize="' . $mapsize . '"' . "\n");
fwrite($fp, 'seed="' . $mapseed . '"' . "\n");
fwrite($fp, 'servername="[AU] ' . $wipedate . ' Snacks | Vanilla | No Tech Tree"' . "\n");
fclose($fp);

generateWipeMessage();

sendDiscordMessage($getCfg('rust.server') . ' Server Map-Wipe initiated (SEED: ' . $weeklyseed . ')...');
shell_exec('cd /home/rust/server && ./rustserver map-wipe');
sendDiscordMessage($getCfg('rust.server') . ' Server Map-Wipe Completed');

sendDiscordMessage($getCfg('rust.server') . ' Server Starting...');
shell_exec('cd /home/rust/server && ./rustserver start');

sendDiscordMessage($getCfg('rust.server') . ' Server wiped - you can connect in 5 minutes from this announcement :tada: (SEED: ' . $mapseed . ' - ' . $mapsize . 'm)', $getCfg('discord.announce'));

$execFinish = microtime(true);
$runTime = ($execFinish - $execStart) / 60;
$runTime = number_format((float) $runTime, 10);

// insert new wipe record in the DB
$sqlWipe = "INSERT INTO `rust_wipes`
(`server`, `address`, `size`, `seed`, `action`, `duration`) VALUES
('" . $getCfg('rust.server') . "', '" . $getCfg('rust.address') . "', '" . $mapsize . "', '" . $mapseed ."', 'Server Wiped', '" . $runTime . "')";

$result = $mysqli->query($sqlWipe);

$mysqli->close();

// end of file
