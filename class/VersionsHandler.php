<?php

namespace XoopsModules\Wgsitenotice;

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

use XoopsModules\Wgsitenotice\Helper;

\defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/*
 * Class Object Handler Versions
 */
class VersionsHandler extends \XoopsPersistableObjectHandler
{
    /**
     * Constructor
     *
     * @param \XoopsDatabase $db
     */
    public function __construct(\XoopsDatabase $db)
    {
        parent::__construct($db, 'wgsitenotice_versions', Versions::class, 'version_id', 'version_name');
    }

    /**
     * create a unique slug for version
     *
     * @param string $title
     * @param int $versionId
     * @return string
     */
    function createUniqueVersionSlug(string $title, int $versionId = 0)
    {
        global $xoopsDB;

        // normalize title and convert to lower case
        $slug = trim(mb_strtolower($title, 'UTF-8'));

        if (class_exists(\Transliterator::class)) {
            /*
             * Transliteration
             * Any-Latin:
             *   Chinese, Cyrillic, Greek ....
             *
             * Latin-ASCII:
             *   é → e
             *   ä → a
             *   ñ → n
             *   ç → c
             */
            $transliterator = \Transliterator::create(
                'Any-Latin; Latin-ASCII'
            );

            if ($transliterator !== null) {
                $slug = $transliterator->transliterate($slug);
            }
        }

        /* replace non-characters/non-digits by - */
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        // if nothing left
        if ($slug === '') {
            $slug = 'version';
        }

        $slug = $xoopsDB->escape($slug);
        $baseSlug = $slug;
        $counter = 1;

        /* Check whether slug already exists */
        while (true) {
            if ($this->checkSlugUnique($slug, $versionId)) {
                break;
            } else {
                $slug = $baseSlug . '-' . $counter;
                if (strlen($slug) > 100) {
                    $slug = substr($baseSlug, 0, 100 - strlen($counter) - 1) . '-' . $counter;
                }
                $counter++;
            }
        }

        return $slug;
    }

    /**
     * check the slug uniqueness
     *
     * @param string $slug
     * @param int $versionId
     * @return bool
     */
    function checkSlugUnique(string $slug, int $versionId = 0)
    {
        global $xoopsDB;

        $sql = '
            SELECT version_id
            FROM ' . $xoopsDB->prefix('wgsitenotice_versions'). "
            WHERE version_slug = '$slug'
        ";
        /* the current version should not be compared */
        if ($versionId > 0) {
            $sql .= ' AND version_id != ' . (int)$versionId;
        }

        $result = $xoopsDB->query($sql);
        if ($xoopsDB->getRowsNum($result) == 0) {
            return true;
        }

        return false;
    }

    /**
     * get version id based on given slug
     *
     * @param string $slug
     *
     * @return int
     */
    function getIdBySlug(string $slug)
    {
        global $xoopsDB;

        $slug = $xoopsDB->escape($slug);

        $sql = '
            SELECT version_id
            FROM ' . $xoopsDB->prefix('wgsitenotice_versions'). "
            WHERE version_slug = '$slug'
        ";

        $result = $xoopsDB->query($sql);
        if (!$xoopsDB->isResultSet($result) || !($result instanceof \mysqli_result)) {
            throw new \RuntimeException(
                \sprintf(_DB_QUERY_ERROR, $sql) . $xoopsDB->error(),
                E_USER_ERROR,
            );
        }
        while (false !== ($row = $xoopsDB->fetchRow($result))) {
            return (int)$row[0];
        }

        return 0;
    }
}
