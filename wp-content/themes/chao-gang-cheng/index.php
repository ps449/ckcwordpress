<?php
/**
 * Main Index Template Fallback
 *
 * @package Chao_Gang_Cheng
 */

get_header(); ?>

<div class="woocommerce-page-wrapper">
    <div class="container">
        <?php
        if ( have_posts() ) :
            while ( have_posts() ) : the_post();
                the_title( '<h1>', '</h1>' );
                the_content();
            endwhile;
        endif;
        ?>
    </div>
</div>

<?php get_footer(); ?>
