<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gibbon\Domain\System;

use Gibbon\View\AssetBundle;

/**
 * Gibbon Theme Model.
 *
 * @version v17
 * @since   v17
 */
class Theme
{
    protected $gibbonThemeID;
    protected $name;
    protected $version;

    protected $stylesheets;
    protected $scripts;

    public function __construct(array $params = [])
    {
        // Merge constructor params into class properties
        foreach ($params as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        $this->stylesheets = new AssetBundle();
        $this->scripts = new AssetBundle();

        $this->stylesheets()->add(
            'theme',
            'themes/'.$this->name.'/css/main.css',
            ['version' => $this->version]
        );
        $this->scripts()->add(
            'theme',
            'themes/'.$this->name.'/js/common.js',
            ['version' => $this->version]
        );
    }

    /**
     * Get the gibbonThemeID
     *
     * @return string
     */
    public function getID()
    {
        return $this->gibbonThemeID;
    }

    /**
     * Get the theme name, used in the folder path and database record.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the collection of stylesheets used by this theme.
     *
     * @return AssetBundle
     */
    public function stylesheets()
    {
        return $this->stylesheets;
    }

    /**
     * Returns the collection of scripts used by this theme.
     *
     * @return AssetBundle
     */
    public function scripts()
    {
        return $this->scripts;
    }
}
