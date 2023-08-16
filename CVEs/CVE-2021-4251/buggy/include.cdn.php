<?php
//always make sure we have a scriptNest variable (can be used to change default path for non-pathed modules
if(!isset($scriptNest)) {
	$scriptNest = '';
}
?>

<script>
    if (typeof console === "undefined") {
        console = {};
        if(typeof console.log === "undefined") {
            console.log = function() {};
        }
    }

    window.AS_APP = '<?php echo AS_APP; ?>';

    function getFullURL() {
        return '<?php echo trim( getFullURL(), '/'); ?>';
    }

    function getBaseURL() {
        return '<?php echo getBaseURL(); ?>';
    }

    function getThemeURL() {
        return '<?php echo getThemeURL(); ?>';
    }

    function getCdnURL() {
        return '<?php echo $_SESSION[AS_APP]['environment']['cdn']; ?>';
    }

	<?php if($_SESSION[AS_APP]['localtesting']) { ?>
    $_SESSION = <?php echo json_encode($_SESSION); ?>;
	<?php } ?>
</script>

<script src="<?php echo $_SESSION[AS_APP]['environment']['cdn']; ?>/require.js"></script>

<script>
    //configure require js
    requirejs.config({
		<?php //By default load any module IDs from js/lib ?>
        baseUrl: getBaseURL()+'/script<?php echo $scriptNest; ?>',
        paths: {

            async: getCdnURL()+'/requirejs/async',
            goog: getCdnURL()+'/requirejs/goog',
            domReady: getCdnURL()+'/requirejs/domReady',

            jquery: getCdnURL()+'/node_modules/jquery/dist/jquery.min',
            jQuery: getCdnURL()+'/node_modules/jquery/dist/jquery.min',

            moment: getCdnURL()+'/node_modules/moment/min/moment.min',

            'es6-promise': getCdnURL()+'/node_modules/es6-promise/dist/es6-promise.min',
            axios: getCdnURL()+'/node_modules/axios/dist/axios.min',

	        <?php if($_SESSION[AS_APP]['localtesting']) { ?>
                vue: getCdnURL()+'/node_modules/vue/dist/vue',
            <?php } else { ?>
                vue: getCdnURL()+'/node_modules/vue/dist/vue.min',
            <?php } ?>

            semanticui: getBaseURL()+"/dcfront/script/rlibs/plugins/sa/semanticui/semantic.min",

            script: getBaseURL()+'/script',

            cdn: getCdnURL()
        }
    });

	<?php foreach($requireJSModules as $module) { ?>
    requirejs(['<?php echo $module; ?>'], function( a ) {
        if(typeof(a)=='function') {
            a();
        }
    });
	<?php } ?>
</script>