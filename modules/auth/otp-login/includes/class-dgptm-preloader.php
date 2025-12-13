<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_head', function(){
    if ( ! (int) dgptm_get_option( 'dgptm_preloader_enabled', 1 ) ) return;
    $logo = (string) dgptm_get_option( 'dgptm_preloader_logo', '' );
    ?>
    <style id="dgptm-preloader-css">
    #dgptm-preloader{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.98);z-index:99999;transition:opacity .25s ease}
    #dgptm-preloader img{width:96px;height:96px;object-fit:contain;animation:dgptmspin 1.2s linear infinite}
    @keyframes dgptmspin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
    .dgptm-preloader-hide{opacity:0;pointer-events:none}
    </style>
    <div id="dgptm-preloader" aria-hidden="true" style="display:none">
        <?php if ( $logo ) : ?>
            <img src="<?php echo esc_url( $logo ); ?>" alt="Loading" />
        <?php else : ?>
            <svg width="96" height="96" viewBox="0 0 24 24" role="img" aria-label="Loading">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" opacity=".2"/>
                <path d="M22 12a10 10 0 0 1-10 10" fill="none" stroke="currentColor" stroke-width="2"/>
            </svg>
        <?php endif; ?>
    </div>
    <script>(function(){var el=document.getElementById('dgptm-preloader');if(!el)return;document.addEventListener('DOMContentLoaded',function(){el.style.display='flex';setTimeout(function(){el.classList.add('dgptm-preloader-hide');},350);});})();</script>
    <?php
});

// Optional: show minimal preloader on login page too
add_action( 'login_head', function(){
    if ( ! (int) dgptm_get_option( 'dgptm_preloader_enabled', 1 ) ) return;
    echo '<style>#dgptm-preloader{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:#fff;z-index:99999}</style><div id="dgptm-preloader"></div><script>document.addEventListener("DOMContentLoaded",function(){var el=document.getElementById("dgptm-preloader"); if(el){setTimeout(function(){el.style.display="none"},350);}});</script>';
});
