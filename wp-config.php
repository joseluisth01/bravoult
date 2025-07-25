<?php
define( 'WP_CACHE', true );

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
define( 'DB_NAME', "autobusmedinaaza_admin" );

/** Database username */
define( 'DB_USER', "autobusmedinaaza_user" );

/** Database password */
define( 'DB_PASSWORD', "sabcJ=d9!xu@icR2" );

/** Database hostname */
define( 'DB_HOST', "localhost" );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'ST],mz7B^k1<$j3V>*SMT`{v$jC/3L74PkA`9K.,oEAH-bm`l8yR]Q%-xf{s.Lfl' );
define( 'SECURE_AUTH_KEY',  '8>N4]C0eI,!e@?PI[6)~LptC:;:V2V_Vyuj~Y*j:Y/(ngU1)**}%;J=1qH?)X6Ss' );
define( 'LOGGED_IN_KEY',    '#0n1sL]SaDt8K|t)AJdup~Hnnohj^C@dU2gDj}`y;Wa8b)Dru|qj({oUX|<QeU<A' );
define( 'NONCE_KEY',        'Ww#MbnrCR>e8VA1:rX@]a>=`F@j`&D;yc<uFy?q*Voc`4+M(cTtiF@pe^<v[=6#Q' );
define( 'AUTH_SALT',        'N|EA[.D.W0u<o6pqhwgI=JT%S=9T7;jm3W(PZJ>w%r|S n: LPO+#s[x]9hF8YM=' );
define( 'SECURE_AUTH_SALT', 'rAL/d}8}1/ho2O=/Y]#lv@|F/Xwu<^]2,4]oiPEUfti?i7IZ`Ge}M>uFQx+y^-iW' );
define( 'LOGGED_IN_SALT',   'K|C:N,S+&6a^xrq|U/pUZO0JG41W]X53eaKwhefi@TJI1=|F;c;54S-9kkxgc jN' );
define( 'NONCE_SALT',       'TTd iAd1)@c$vwB7F$U|m.Jft9~R+h*pg 7^=:sJq1vaGE3m+ApA&U.?*6xVV5[%' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
// Añade estas líneas ANTES de la línea "That's all, stop editing!"
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false ); // Importante: false para AJAX
@ini_set( 'display_errors', 0 );

// Para ver errores en AJAX - SOLO PARA DEBUGGING
define( 'SCRIPT_DEBUG', true );

// Limpiar cualquier output buffer que interfiera con AJAX
if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
    @ini_set( 'display_errors', 0 );
}

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
