<?php
/**
 * Template Name: React DB Blank
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>" />
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
while ( have_posts() ) :
    the_post();
    the_content();
endwhile;
wp_footer();
?>
</body>
</html>
