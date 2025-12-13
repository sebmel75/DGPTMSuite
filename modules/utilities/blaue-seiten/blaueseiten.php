<?php
/*
Plugin Name: DGPTM Blue Newsbereich Plugin
Description: Zeigt Beiträge des Post-Types "newsbereich" in der Kategorie "blaueseiten" mit Toggle (Mehr/Weniger), Infinite Scroll, gruppierter Sidebar und zwei Shortcodes ([custom_posts_page] und [list_blaue_seiten]). [custom_posts_page] ist nur für Administrator, Editor und Mitglied zugänglich – Beiträge werden trotz public = false angezeigt.
Version: 1.9
Author: Seb.
*/

// Direkten Aufruf verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function dgptmblue_render_single_post( $post ) {
    $post_id = $post->ID;
    $title   = get_the_title( $post_id );
    $date    = get_the_date( 'd.m.Y', $post_id );
    $excerpt = wp_trim_words( $post->post_content, 50, '...' );
    
    $html  = '<div class="dgptmblue-post" data-post-id="' . $post_id . '" id="post-' . $post_id . '" style="margin-bottom: 40px;">';
    $html .= '<h3 class="dgptmblue-title">' . esc_html( $title ) . '</h3>';
    $html .= '<small class="dgptmblue-date" style="display:block; font-size:0.8em; color:#777;">' . $date . '</small>';
    // Verstecktes Element für den gekürzten Inhalt (wird beim Einklappen genutzt)
    $html .= '<div class="dgptmblue-excerpt" style="display:none;">' . $excerpt . '</div>';
    // Sichtbarer Inhaltsbereich (initial: gekürzter Inhalt)
    // Hier könnte man overflow-wrap ergänzen; machen wir aber in globalem CSS
    $html .= '<div class="dgptmblue-content">' . $excerpt . '</div>';
    // Toggle-Button
    $html .= '<button class="dgptmblue-toggle" data-post-id="' . $post_id . '" data-expanded="false" style="font-size:0.8em; margin-top:10px;">Mehr anzeigen</button>';
    $html .= '</div>';
    
    return $html;
}

function dgptmblue_enqueue_scripts() {
    wp_enqueue_script( 'jquery' );
    
    wp_register_script( 'dgptmblue-ajax-script', '', array( 'jquery' ), '1.9', true );
    wp_enqueue_script( 'dgptmblue-ajax-script' );
    
    wp_localize_script( 'dgptmblue-ajax-script', 'dgptmblue_ajax_obj', array(
        'ajax_url' => admin_url( 'admin-ajax.php' )
    ) );
    
    $inline_js = "
    jQuery(document).ready(function($){
        
        function openPostInMainArea(postId) {
            var \$postElement = $('.dgptmblue-main').find('.dgptmblue-post[data-post-id=\"' + postId + '\"]');
            if (\$postElement.length) {
                $('html, body').animate({scrollTop: \$postElement.offset().top - 50}, 500);
                var \$toggle = \$postElement.find('.dgptmblue-toggle');
                if (!\$toggle.data('expanded')) {
                    \$toggle.trigger('click');
                }
            } else {
                $.ajax({
                    url: dgptmblue_ajax_obj.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'dgptmblue_load_single_post',
                        post_id: postId
                    },
                    success: function(response) {
                        if (response.success) {
                            \$('.dgptmblue-main').prepend(response.data.html);
                            var \$newPost = \$('.dgptmblue-main').find('.dgptmblue-post[data-post-id=\"' + postId + '\"]');
                            $('html, body').animate({scrollTop: \$newPost.offset().top - 50}, 500);
                            var \$toggle = \$newPost.find('.dgptmblue-toggle');
                            if (!\$toggle.data('expanded')) {
                                \$toggle.trigger('click');
                            }
                        } else {
                            alert('Fehler beim Laden des Beitrags (Sidebar/Hash).');
                        }
                    },
                    error: function() {
                        alert('Ajax Fehler beim Laden des Beitrags (Sidebar/Hash).');
                    }
                });
            }
        }
        
        // Toggle Mehr/Weniger
        \$('.dgptmblue-main').on('click', '.dgptmblue-toggle', function(e){
            e.preventDefault();
            var \$btn = \$(this);
            var \$post = \$btn.closest('.dgptmblue-post');
            var \$contentDiv = \$post.find('.dgptmblue-content');
            
            if (\$btn.data('expanded')) {
                var excerpt = \$post.find('.dgptmblue-excerpt').html();
                \$contentDiv.html(excerpt);
                \$btn.text('Mehr anzeigen');
                \$btn.data('expanded', false);
            } else {
                // Andere öffnen = zu
                \$('.dgptmblue-main .dgptmblue-toggle').each(function(){
                    if (\$(this).data('expanded') && this !== \$btn[0]) {
                        var \$otherPost = \$(this).closest('.dgptmblue-post');
                        var otherExcerpt = \$otherPost.find('.dgptmblue-excerpt').html();
                        \$otherPost.find('.dgptmblue-content').html(otherExcerpt);
                        \$(this).text('Mehr anzeigen');
                        \$(this).data('expanded', false);
                    }
                });
                
                if (\$post.data('full')) {
                    \$contentDiv.html(\$post.data('full'));
                    \$btn.text('Weniger anzeigen');
                    \$btn.data('expanded', true);
                    $('html, body').animate({scrollTop: \$post.find('.dgptmblue-title').offset().top - 50}, 500);
                } else {
                    var postId = \$btn.data('post-id');
                    \$btn.prop('disabled', true);
                    \$.ajax({
                        url: dgptmblue_ajax_obj.ajax_url,
                        method: 'POST',
                        data: {
                            action: 'dgptmblue_load_full_content',
                            post_id: postId
                        },
                        success: function(response) {
                            if (response.success) {
                                \$post.data('full', response.data.full_content);
                                \$contentDiv.html(response.data.full_content);
                                \$btn.text('Weniger anzeigen');
                                \$btn.data('expanded', true);
                                $('html, body').animate({scrollTop: \$post.find('.dgptmblue-title').offset().top - 50}, 500);
                            } else {
                                alert('Fehler beim Laden des Inhalts.');
                            }
                            \$btn.prop('disabled', false);
                        },
                        error: function() {
                            alert('Ajax Fehler beim Laden des Inhalts.');
                            \$btn.prop('disabled', false);
                        }
                    });
                }
            }
        });
        
        // Sidebar-Klick
        \$('.dgptmblue-sidebar').on('click', 'a', function(e){
            e.preventDefault();
            var postId = \$(this).data('post-id');
            if (\$('.dgptmblue-sidebar').hasClass('dgptmblue-sidebar-open')) {
                \$('.dgptmblue-sidebar').removeClass('dgptmblue-sidebar-open');
            }
            openPostInMainArea(postId);
        });
        
        // Infinite Scroll
        var currentPage = 2;
        var loadingMore = false;
        \$(window).on('scroll', function(){
            if (!loadingMore && \$(window).scrollTop() + \$(window).height() > \$('.dgptmblue-main').offset().top + \$('.dgptmblue-main').outerHeight() - 200) {
                loadingMore = true;
                \$.ajax({
                    url: dgptmblue_ajax_obj.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'dgptmblue_load_more_posts',
                        page: currentPage
                    },
                    success: function(response) {
                        if (response.success && response.data.html) {
                            \$('.dgptmblue-main').append(response.data.html);
                            currentPage++;
                        }
                        loadingMore = false;
                    },
                    error: function() {
                        loadingMore = false;
                    }
                });
            }
        });
        
        // Hash
        function openPostByHash() {
            var hash = window.location.hash;
            if (hash && hash.indexOf('#post-') === 0) {
                var postId = hash.replace('#post-', '');
                openPostInMainArea(postId);
            }
        }
        openPostByHash();
        
        // Collapsible Monate
        \$('.dgptmblue-sidebar-box').on('click', '.dgptmblue-month-heading', function(){
            \$(this).next('.dgptmblue-month-list').slideToggle();
            var icon = \$(this).find('.toggle-icon');
            icon.text(icon.text() === '+' ? '-' : '+');
        });
        
        // Mobile Toggle
        \$('#dgptmblue-mobile-menu-btn').on('click', function(e){
            e.preventDefault();
            \$('.dgptmblue-sidebar').toggleClass('dgptmblue-sidebar-open');
        });
        \$('#dgptmblue-sidebar-close-btn').on('click', function(e){
            e.preventDefault();
            \$('.dgptmblue-sidebar').removeClass('dgptmblue-sidebar-open');
        });
    });
    ";
    wp_add_inline_script( 'dgptmblue-ajax-script', $inline_js );
}
add_action( 'wp_enqueue_scripts', 'dgptmblue_enqueue_scripts' );

/** AJAX-Handler: Volltext usw. */
function dgptmblue_load_full_content() {
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if ( ! $post_id ) {
        wp_send_json_error();
    }
    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_send_json_error();
    }
    $full_content = apply_filters( 'the_content', $post->post_content );
    wp_send_json_success( array( 'full_content' => $full_content ) );
}
add_action( 'wp_ajax_dgptmblue_load_full_content', 'dgptmblue_load_full_content' );
add_action( 'wp_ajax_nopriv_dgptmblue_load_full_content', 'dgptmblue_load_full_content' );

function dgptmblue_load_single_post_ajax() {
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if ( ! $post_id ) {
        wp_send_json_error();
    }
    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_send_json_error();
    }
    $html = dgptmblue_render_single_post( $post );
    wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_dgptmblue_load_single_post', 'dgptmblue_load_single_post_ajax' );
add_action( 'wp_ajax_nopriv_dgptmblue_load_single_post', 'dgptmblue_load_single_post_ajax' );

function dgptmblue_load_more_posts_ajax() {
    $page = isset( $_POST['page'] ) ? intval($_POST['page']) : 1;
    $args = array(
        'post_type'      => 'newsbereich',
        'posts_per_page' => 5,
        'category_name'  => 'blaueseiten',
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'post_status'    => 'publish'
    );
    $query = new WP_Query( $args );
    
    $html = '';
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
           $query->the_post();
           $html .= dgptmblue_render_single_post( get_post() );
        }
        wp_reset_postdata();
    }
    wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_dgptmblue_load_more_posts', 'dgptmblue_load_more_posts_ajax' );
add_action( 'wp_ajax_nopriv_dgptmblue_load_more_posts', 'dgptmblue_load_more_posts_ajax' );

/** Shortcode-Hauptseite */
function dgptmblue_render_page() {
    if ( ! is_user_logged_in() || ! ( current_user_can('administrator') || current_user_can('editor') || current_user_can('mitglied') ) ) {
        return '<p>Diese Seite ist nur für Mitglieder zugänglich.</p>';
    }
    
    ob_start();
    ?>
   <style>
  /*================================
    Custom Properties
  =================================*/
  :root {
    --wp-admin-bar-height: 32px;

  }
	   

  /*================================
    Reset & Grundregeln
  =================================*/
  html, body {
    margin: 0;
    padding: 0;
    overflow-x: hidden; /* Verhindert horizontales Scrollen */
  }
  .dgptmblue-container,
  .dgptmblue-main,
  .dgptmblue-sidebar,
  .dgptmblue-post {
    box-sizing: border-box;
    max-width: 100%;
  }
  .dgptmblue-content {
    overflow-wrap: break-word;
    word-wrap: break-word;
  }

  .dgptmblue-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    width: 100%;
  }
  .dgptmblue-main {
    flex: 3;
    min-width: 300px;
    width: 100%;
  }
  .dgptmblue-sidebar {
    flex: 1;
    min-width: 200px;
    /* Desktop-Verhalten in Media-Query */
  }

  /*================================
    Sidebar-Box & Monats-Liste
  =================================*/
  .dgptmblue-sidebar-box {
    border: 1px solid #ccc;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 10px;
    font-size: 0.8em;
    background-color: rgba(255, 255, 255, 0.2);
  }
  .dgptmblue-month-heading {
    cursor: pointer;
    background-color: rgba(255, 255, 255, 0.4);
    border: 1px solid #ccc;
    padding: 5px;
    margin: 0;
  }
  .dgptmblue-month-heading .toggle-icon {
    float: right;
    font-weight: bold;
  }
  .dgptmblue-month-list {
    margin: 0;
    padding: 0 10px;
  }
  .dgptmblue-sidebar-box li a {
    font-size: 1.2em;
  }

  /*================================
     DESKTOP (ab 769px):
     Fixed-Sidebar + Main-Margin
  =================================*/
  @media (min-width: 769px) {
    .dgptmblue-sidebar {
      position: fixed;
      top: calc(var(--wp-admin-bar-height) + 200px);
      right: 20px;
      width: 300px; /* Breite der Sidebar */
    }
    .dgptmblue-main {
      margin-right: calc(300px + 40px);
      /* Breite Sidebar (300px) + 2×20px Abstand */
    }
  }

  /*================================
     MOBILE (bis 768px):
     Off‑Canvas Sidebar
  =================================*/
  @media (max-width: 768px) {
    .dgptmblue-container {
      flex-direction: column;
    }
    .dgptmblue-sidebar {
      position: fixed;
      top: 0;
      right: 0;
      width: 80%;
      max-width: 300px;
      height: 100%;
      background: #fff;
      transform: translateX(100%);
      z-index: 9999;
      overflow-y: auto;
      box-shadow: -2px 0 5px rgba(0,0,0,0.2);
    }
    .dgptmblue-sidebar.dgptmblue-sidebar-open {
      transform: translateX(0);
    }
    #dgptmblue-sidebar-close-btn {
      display: block;
      text-align: right;
      margin: 10px 10px 10px 0;
      color: #000;
      text-decoration: none;
      font-weight: bold;
    }
  }
</style>


    <div class="dgptmblue-container">
        <!-- Hauptinhalte / Beiträge -->
        <div class="dgptmblue-main">
            <?php
            // Mobiler Button zum Öffnen der Sidebar
         //   echo '<a href="#" id="dgptmblue-mobile-menu-btn" style="display:none;">Alle Beiträge</a>';
            
            $args = array(
                'post_type'      => 'newsbereich',
                'posts_per_page' => 5,
                'category_name'  => 'blaueseiten',
                'orderby'        => 'date',
                'order'          => 'DESC',
                'post_status'    => 'publish'
            );
            $query = new WP_Query( $args );
            if ( $query->have_posts() ) {
                while ( $query->have_posts() ) {
                    $query->the_post();
                    echo dgptmblue_render_single_post( get_post() );
                }
                wp_reset_postdata();
            } else {
                echo '<p>Keine Beiträge gefunden.</p>';
            }
            ?>
        </div>
        
        <!-- Sidebar -->
        <div class="dgptmblue-sidebar">
            <!-- In mobiler Ansicht: ein Close-Button -->
            <a href="#" id="dgptmblue-sidebar-close-btn" style="display:none;">× Schließen</a>
            
            <h4>Alle Beiträge</h4>
            <div class="dgptmblue-sidebar-box">
                <?php
                    $args_sidebar = array(
                        'post_type'      => 'newsbereich',
                        'posts_per_page' => 10,
                        'category_name'  => 'blaueseiten',
                        'orderby'        => 'date',
                        'order'          => 'DESC',
                        'post_status'    => 'publish'
                    );
                    $sidebar_query = new WP_Query( $args_sidebar );
                    if ( $sidebar_query->have_posts() ) {
                        $current_group = '';
                        $first = true;
                        while ( $sidebar_query->have_posts() ) {
                            $sidebar_query->the_post();
                            $group = get_the_date('F Y');
                            if ( $group !== $current_group ) {
                                if ( ! empty($current_group) ) {
                                    echo '</ul>';
                                }
                                if ( $first ) {
                                    $toggle_icon = '-';
                                    $display = '';
                                } else {
                                    $toggle_icon = '+';
                                    $display = ' style="display:none;"';
                                }
                                echo '<h5 class="dgptmblue-month-heading">' . esc_html($group) . ' <span class="toggle-icon">' . $toggle_icon . '</span></h5>';
                                echo '<ul class="dgptmblue-month-list"' . $display . '>';
                                $current_group = $group;
                                $first = false;
                            }
                            echo '<li><a href="#" data-post-id="' . get_the_ID() . '">' . get_the_title() . '</a></li>';
                        }
                        if ( ! empty($current_group) ) {
                            echo '</ul>';
                        }
                        wp_reset_postdata();
                    }
                ?>
            </div>
        </div>
    </div>
    <script>
    (function() {
        function handleResize() {
            var menuBtn = document.getElementById('dgptmblue-mobile-menu-btn');
            var closeBtn = document.getElementById('dgptmblue-sidebar-close-btn');
            if (window.innerWidth <= 768) {
                if (menuBtn) menuBtn.style.display = 'inline-block';
                if (closeBtn) closeBtn.style.display = 'block';
            } else {
                if (menuBtn) menuBtn.style.display = 'none';
                if (closeBtn) closeBtn.style.display = 'none';
            }
        }
        window.addEventListener('resize', handleResize);
        window.addEventListener('DOMContentLoaded', handleResize);
        window.addEventListener('load', handleResize);
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'custom_posts_page', 'dgptmblue_render_page' );

/**
 * Shortcode [list_blaue_seiten]:
 */
function dgptmblue_render_list_blaue_seiten() {
    ob_start();
    $args = array(
        'post_type'      => 'newsbereich',
        'posts_per_page' => 5,
        'category_name'  => 'blaueseiten',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'post_status'    => 'publish'
    );
    $query = new WP_Query( $args );
    if ( $query->have_posts() ) {
        echo '<ul class="dgptmblue-blaue-seiten-list">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $link = '/interner-bereich/blaue-seiten/#post-' . get_the_ID();
            echo '<li><a href="' . esc_url( $link ) . '">' . get_the_title() . '</a></li>';
        }
        echo '</ul>';
        wp_reset_postdata();
    } else {
        echo '<p>Keine Beiträge für "blaueseiten" gefunden.</p>';
    }
    return ob_get_clean();
}
add_shortcode( 'list_blaue_seiten', 'dgptmblue_render_list_blaue_seiten' );
?>
