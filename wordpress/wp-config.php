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
define( 'DB_NAME', 'novo_db' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

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
define( 'AUTH_KEY',         'X5gAgA{xzuS9n6nq_/{E_8;ud$yaSXqR4OuW 6EfQeD%#w6X=|TT$p=Uh&9.4%WD' );
define( 'SECURE_AUTH_KEY',  ':QuL -^?*8zE-~;5`|7lBU7b>(G,l8fO<N*GsdGRCFORZ@Luc<{&:A`9;}5i_;{>' );
define( 'LOGGED_IN_KEY',    '$^;^[|6%#8iQA[fOI_o[C9u@kp)9=PbB;Aq.<1{D[`2 u9V{0v,t[t#4I9pSf-5<' );
define( 'NONCE_KEY',        'IA{;2kzGp9LMv:{TA%Tu]1Q$YUHt7SLox4PYkZHiM-Ms3JY FV1_op7|(;V [q}h' );
define( 'AUTH_SALT',        'd*:ihQxqblr`L2$n?q[<!Vv`4?Nak<=!-9L,BxoMr]+!KT(|-(CZ3T:`yU)5ZC:/' );
define( 'SECURE_AUTH_SALT', ' B|<|c7qiy[T][MAJtKUi_PV^x?5VVDE~thqZXeV?/cAp^c>4BR$ 5.;WlChv9[:' );
define( 'LOGGED_IN_SALT',   'nNct(7J!*kPPqK_^2+V5&J6 *ctfhvNf_>HJ^XuQlaypjkUzdq)F,DX%5NO~~j9>' );
define( 'NONCE_SALT',       'Paq0h 81l<u]PUPuo.4geOgJLH/?X,0 cOa_~_0YhiFgl7} cUYlfm_J?r *E*}1' );

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
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
