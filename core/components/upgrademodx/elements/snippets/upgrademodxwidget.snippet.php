<?php
/**
 * UpgradeModxWidget snippet for UpgradeModx extra
 *
 * Copyright 2015 by Bob Ray <http://bobsguides.com>
 * Created on 08-16-2015
 *
 * UpgradeModx is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * UpgradeModx is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * UpgradeModx; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package upgrademodx
 */

/**
 * Description
 * -----------
 * UpgradeModx Dashboard widget
 *
 * Variables
 * ---------
 * @var $modx modX
 * @var $scriptProperties array
 *
 * @package upgrademodx
 **/
class UpgradeModx
{

    /** @var $versionlist string - array of versions to display if update is available as a string
     *  to inject into upgrade script */
    public $versionList = '';

    /** @var $snippetObject modSnippet - widget snippet (we need its properties) */
    protected $snippetObject = null;

    /** @var $modx modX - modx object */
    protected $modx = null;

    /** @var $latestVersion string - latest version available; set only if an upgrade */
    protected $latestVersion = '';

    /**
     *
     */
    const CHECK_SUCCESS = 0;
    const CHECK_TIMEOUT = 1;
    const CHECK_EMPTY = 2;


    public function __construct($modx)
    {
        $this->modx = $modx;
    }


    /**
     * @param $lastCheck string = time of previous check
     * @param $interval - interval between checks
     * @return bool true if time to check, false if not
     */
    public static function timeToCheck($lastCheck, $interval = '+1 week')
    {
        if (empty($lastCheck)) {
            $retVal = true;
        } else {
            $interval = strpos($interval, '+') === false ? '+' . $interval : $interval;
            $retVal = time() > strtotime($lastCheck . ' ' . $interval);
        }
        return $retVal;
    }

    public function getLatestVersion()
    {
        return $this->latestVersion;
    }


    public function upgradeAvailable($currentVersion, $plOnly = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/repos/modxcms/revolution/tags');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "revolution");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 3500);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);

        $contents = curl_exec($ch);

        $contents = json_decode($contents);


        if ($plOnly) { /* remove non-pl version objects */
            foreach ($contents as $key => $content) {
                $name = substr($content->name, 1);
                if (strpos($name, 'pl') === false) {
                    unset($contents[$key]);
                }
            }
        }

        $latestVersionObj = reset($contents);
        $latestVersion = substr($latestVersionObj->name, 1);
        $newVersion = version_compare($currentVersion, $latestVersion) < 0;

        /* ToDo: Do this only if upgrade is available */
        $versions = array();
        $i = 1;
        /*foreach ($contents as $content) {
            $name = substr($content->name, 1);
            if ($plOnly && strpos($name, 'pl') === false) {
               //  continue;
            }
            $url = 'http://modx.com/download/direct/modx-' . $name . '.zip';
            $InstallData[$name] = array(
               'tree'     => 'Revolution',
                'name'     => 'MODX Revolution ' . $name,
                'link'     => $url,
                'location' => 'setup/index.php',
            );
            $i++;
            if ($i > $versionsToShow) {
                break;
            }
        }*/


        $this->versionList = '$InstallData = ' . var_export($this->versionList, true) . ';';


        // echo "\nLatest: " . $latestVersion;
        // echo "\nCurrent: " . $currentVersion;


        $downloadable = false;

// $ch = curl_init();
        if ($newVersion) { /* See if it's available at modx.com/download */
            $this->lastestVersion = $latestVersion;
            $downloadUrl = 'http://modx.com/download/direct/modx-' . $latestVersion . '.zip';

            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
            curl_setopt($ch, CURLOPT_URL, $downloadUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_NOBODY, true);

            $retCode = curl_exec($ch);

            if ($retCode !== false) {
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $downloadable = $statusCode == 200 || $statusCode == 301 || $statusCode == 302;
            }
            curl_close($ch);
        }

        return ($newVersion && $downloadable);
    }

    public function getVersionList()
    {
        return $this->versionList;
    }
}

$upgrade = new UpgradeModx($modx);
$currentVersion = $modx->getOption('settings_version');
$snippet = $modx->getObject('modSnippet', array('name' => 'UpgradeModxWidget'));
if (!$snippet) {
    return '<p style="color:red">Could not find UpgradeModxWidget snippet</p>';
} else {
    $props = $snippet->getProperties();
    if ($props) {
        $lastCheck = $modx->getOption('lastCheck', $props);
        $interval = $modx->getOption('interval', $props);
        $hideWhenNoUpgrade = $modx->getOption('hideWhenNoUpgrade', $props);
        $plOnly = $interval = $modx->getOption('plOnly', $props);
    } else {
        return '<p style="color:red">Could not find UpgradeModxWidget properties</p>';
    }
}
if (!$lastCheck && $interval) {
    return '<p style="color:red">lastCheck or interval properties not set</p>';
}

if (!$upgrade::timeToCheck($lastCheck, $interval)) {
    $currentVersion = $modx->getOption('settings_version');
    $upgradeAvailable = $upgrade->upgradeAvailable($currentVersion, true);
}

if (!$upgradeAvailable) {
    $output = 'UPGRADE AVAILABLE';
} else {
    if ($hideWhenNoUpgrade) {
        return '';
    } else {
        return 'Upgrade Available<br/> Current Version: ' . $currentVersion .
        '<br />Latest Version: ' . $upgrade->getLatestVersion();

    }
}