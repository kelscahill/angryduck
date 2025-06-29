<?php
define( 'DISABLE_JETPACK_WAF', false );
if ( defined( 'DISABLE_JETPACK_WAF' ) && DISABLE_JETPACK_WAF ) return;
define( 'JETPACK_WAF_MODE', 'silent' );
define( 'JETPACK_WAF_SHARE_DATA', false );
define( 'JETPACK_WAF_SHARE_DEBUG_DATA', false );
<<<<<<< HEAD
define( 'JETPACK_WAF_DIR', '/Users/kelseycahill/Sites/Angry Duck/app/public/wp-content/jetpack-waf' );
define( 'JETPACK_WAF_WPCONFIG', '/Users/kelseycahill/Sites/Angry Duck/app/public/wp-content/../wp-config.php' );
define( 'JETPACK_WAF_ENTRYPOINT', 'rules/rules.php' );
require_once '/Users/kelseycahill/Sites/Angry Duck/app/public/wp-content/plugins/jetpack/vendor/autoload.php';
=======
define( 'JETPACK_WAF_DIR', '/www/angryduckrevolution_569/public/wp-content/jetpack-waf' );
define( 'JETPACK_WAF_WPCONFIG', '/www/angryduckrevolution_569/public/wp-content/../wp-config.php' );
define( 'JETPACK_WAF_ENTRYPOINT', 'rules/rules.php' );
require_once '/www/angryduckrevolution_569/public/wp-content/plugins/jetpack/vendor/autoload.php';
>>>>>>> 2a5732e89169eadb82b3553175e0f2d68852dc26
Automattic\Jetpack\Waf\Waf_Runner::initialize();
