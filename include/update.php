<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/
/**
 * wgSitenotice module for xoops
 *
 * @copyright       XOOPS Project (https://xoops.org)
 * @license         GPL 2.0 or later
 * @package         wgsitenotice
 * @author          Goffy (xoops.wedega.com) - Email:<webmaster@wedega.com> - Website:<https://xoops.wedega.com>
 */
/**
 * @param      $module
 * @param null $prev_version
 *
 * @return bool|null
 */

use XoopsModules\Wgsitenotice\Common\ {
    Configurator,
    Migrate,
    MigrateHelper
};
use XoopsModules\Wgsitenotice\Helper;

function xoops_module_update_wgsitenotice($module, $prev_version = null)
{

    $moduleDirName = $module->dirname();

    $configurator = new Configurator();
    $migrate = new Migrate($configurator);

    $fileSql = \XOOPS_ROOT_PATH . '/modules/' . $moduleDirName . '/sql/mysql.sql';
    // ToDo: add function setDefinitionFile to .\class\libraries\vendor\xoops\xmf\src\Database\Migrate.php
    // Todo: once we are using setDefinitionFile this part has to be adapted
    //$fileYaml = \XOOPS_ROOT_PATH . '/modules/' . $moduleDirName . '/sql/update_' . $moduleDirName . '_migrate.yml';
    //try {
    //$migrate->setDefinitionFile('update_' . $moduleDirName);
    //} catch (\Exception $e) {
    // as long as this is not done default file has to be created
    $moduleVersionOld = $module->getInfo('version');
    $moduleVersionNew = \str_replace(['.', '-'], '_', $moduleVersionOld);
    $fileYaml = \XOOPS_ROOT_PATH . '/modules/' . $moduleDirName . "/sql/{$moduleDirName}_{$moduleVersionNew}_migrate.yml";
    //}

    // create a schema file based on sql/mysql.sql
    $migratehelper = new MigrateHelper($fileSql, $fileYaml);
    if (!$migratehelper->createSchemaFromSqlfile()) {
        \xoops_error('Error: creation schema file failed!');
        return false;
    }

    //create copy for XOOPS 2.5.11 Beta 1 and older versions
    $fileYaml2 = \XOOPS_ROOT_PATH . '/modules/' . $moduleDirName . "/sql/{$moduleDirName}_{$moduleVersionOld}_migrate.yml";
    \copy($fileYaml, $fileYaml2);

    // run standard procedure for db migration
    $migrate->getTargetDefinitions();
    $migrate->synchronizeSchema();

    //check upload directory
    require_once __DIR__ . '/install.php';
    $ret = xoops_module_install_wgsitenotice($module);
    $errors = $module->getErrors();
    foreach ($errors as $error) {
        xoops_error($error);
    }

    if (version_compare($prev_version, '1.5.0', '<')) {
        //enter code or call your function
        $ret = wgsitenotice_update_slug($module);
        if (!$ret) {
            return false;
        }
    }
    // remove temporary files again
    if (file_exists($fileYaml)) {
        unlink($fileYaml);
    }
    if (file_exists($fileYaml2)) {
        unlink($fileYaml2);
    }

    return true;
}


/**
 * @param $module
 *
 * @return bool
 */
function wgsitenotice_update_slug($module): bool
{

    $helper = Helper::getInstance();
    $versionsHandler = $helper->getHandler('Versions');

    if ($versionsHandler->getCount() > 0) {
        $version_crit = new \CriteriaCompo();
        $version_crit->setSort('version_weight ASC, version_id');
        $version_crit->setOrder('ASC');
        $versions_arr = $versionsHandler->getAll($version_crit);
        foreach (\array_keys($versions_arr) as $i)
        {
            // check whether a valid slug exist
            $slugOld = $versions_arr[$i]->getVar('version_slug');
            if ('' == $slugOld) {
                $versionId = $versions_arr[$i]->getVar('version_id');
                $versionName = $versions_arr[$i]->getVar('version_name');
                $slugNew = $versionsHandler->createUniqueVersionSlug($versionName, $versionId);
                $versionsObj = $versionsHandler->get($versionId);
                $versionsObj->setVar('version_slug', $slugNew);
                if (!$versionsHandler->insert($versionsObj)) {
                    return false;
                }
           }
        }
    }

    return true;
}
