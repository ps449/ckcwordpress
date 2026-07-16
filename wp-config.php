<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'sqlite' );

/** Database username */
define( 'DB_USER', 'sqlite' );

/** Database password */
define( 'DB_PASSWORD', 'sqlite' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

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
define( 'AUTH_KEY',          ']3lcOQpE_/zh0QCWI))A7l)cBT@mL8ebzXg_(DeA%WCP4f(%Jvc~)=S*&c~P;>.X' );
define( 'SECURE_AUTH_KEY',   'aYNF0d5s?LIO*}9~z]dxgu!1MNQ.!)G@Y/.2uTe>9J&&<YVJ@WK7la;!y,XG2=P?' );
define( 'LOGGED_IN_KEY',     'lzlS*G~Xk_K]U^|-|c_OCmmx{p`TR,6$.hk?i=zER}w6JIvKEA@yUruadxI Ni4u' );
define( 'NONCE_KEY',         'HVlh$31nP=d/u_bL:_2D<|T}w,((!cV%Y8KHBr97[>OHbGx?TU`IVf}^}k>0XKaS' );
define( 'AUTH_SALT',         '73h#so2TYakXd>e>$X!7zW1_4j`d&f![f-n).KT<Z5Xvil(;*s s#c@OoA%otL84' );
define( 'SECURE_AUTH_SALT',  '|X9opd+uNb[siR^b)Sg%FM GDVE&`yp)-M|B-&rB>ll)-7P>c#gmu4y$D~IOewdf' );
define( 'LOGGED_IN_SALT',    ',09_rJ]L{N[b?y_15&)phPF}hdoN?bI!jj$6r6E~?6c,2xY~k6*HK[4tVKfSMJEG' );
define( 'NONCE_SALT',        'c&Hm<)=bW{^e4ULnzrA/a.xczu:D-Nfr?1K^7/8eH!c_[ZnD8anWOvW0*:o&,wd(' );
define( 'WP_CACHE_KEY_SALT', 'd,0j_Ww2ECte]o@LDu.|S)Oz6`FDM@00.[%{)(a?-:^r]]-!z%T/7jF2wkOwhcw$' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



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
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
