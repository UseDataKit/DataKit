<?php

define( 'WP_SETUP_CONFIG', true );
define( 'WP_INSTALLING', true );
define( 'ABSPATH', dirname( __DIR__ ) . '/vendor/roots/wordpress-no-content/' );

require dirname( __DIR__ ) . '/vendor/autoload.php';
require dirname( __DIR__ ) . '/vendor/roots/wordpress-no-content/wp-settings.php';

$wpdb = new wpdb( '', '', '', '' );
