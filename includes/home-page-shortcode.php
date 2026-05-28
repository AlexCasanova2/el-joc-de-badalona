<?php
/**
 * Shortcode para la nueva landing page premium de El Joc de Badalona.
 */

function ejb_landing_page_shortcode() {
    $is_logged_in = is_user_logged_in();
    $current_user = wp_get_current_user();
    
    // IDs de páginas (basados en functions.php)
    $id_juego = 999;
    $id_ranking = 1033;
    $id_perfil = site_url('/perfil'); // Asumiendo slug /perfil
    $id_login = site_url('/iniciar-sessio');
    
    // Imagen generada por Antigravity
    $hero_image_url = EJB_PLUGIN_URL . 'assets/badalona-hero.png';
    
    ob_start();
    ?>
    <div class="ejb-home-wrapper">
        <!-- Hero Section -->
        <section class="ejb-hero">
            <div class="ejb-hero-overlay"></div>
            <div class="ejb-hero-content">
                <div class="ejb-badge">✨ El repte diari de la ciutat</div>
                <h1 class="ejb-hero-title">Demostra quant <br><span>saps de Badalona</span></h1>
                <p class="ejb-hero-subtitle">Participa cada dia al joc més popular. Respon correctament, guanya punts i escala fins al capdamunt del ranking.</p>
                
                <div class="ejb-hero-actions">
                    <?php if ($is_logged_in): ?>
                        <a href="<?php echo get_permalink($id_juego); ?>" class="ejb-btn ejb-btn-primary">
                            <i data-lucide="play-circle"></i> Començar a jugar
                        </a>
                        <a href="<?php echo esc_url($id_perfil); ?>" class="ejb-btn ejb-btn-outline">
                            <i data-lucide="user"></i> El meu perfil
                        </a>
                    <?php else: ?>
                        <a href="<?php echo esc_url($id_login); ?>" class="ejb-btn ejb-btn-primary">
                            <i data-lucide="log-in"></i> Inicia sessió per jugar
                        </a>
                        <a href="<?php echo site_url('/registre'); ?>" class="ejb-btn ejb-btn-outline">
                            Crear un compte
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Floating Decorative Elements -->
            <div class="ejb-floating-shapes">
                <div class="shape shape-1"></div>
                <div class="shape shape-2"></div>
                <div class="shape shape-3"></div>
            </div>
        </section>

        <!-- Stats / Features Section -->
        <section class="ejb-features">
            <div class="ejb-section-header">
                <h2 class="ejb-section-title">Com funciona?</h2>
                <div class="ejb-section-divider"></div>
            </div>
            
            <div class="ejb-features-grid">
                <div class="ejb-feature-card">
                    <div class="ejb-icon-wrapper">
                        <i data-lucide="calendar"></i>
                    </div>
                    <h3>Cada dia</h3>
                    <p>Noves preguntes sobre cultura, història i actualitat de la nostra ciutat.</p>
                </div>
                
                <div class="ejb-feature-card">
                    <div class="ejb-icon-wrapper">
                        <i data-lucide="zap"></i>
                    </div>
                    <h3>Suma punts</h3>
                    <p>Com més ràpid i precís siguis, més punts aconseguiràs al ranking global.</p>
                </div>
                
                <div class="ejb-feature-card">
                    <div class="ejb-icon-wrapper">
                        <i data-lucide="trophy"></i>
                    </div>
                    <h3>Guanya premis</h3>
                    <p>Els millors classificats de cada mes tindran sorpreses i reconeixements.</p>
                </div>
            </div>
        </section>

        <!-- Ranking Preview / CTA -->
        <section class="ejb-ranking-cta">
            <div class="ejb-glass-card">
                <div class="ejb-glass-content">
                    <div class="ejb-glass-text">
                        <h3>Vols veure qui mana a Badalona?</h3>
                        <p>Consulta el ranking en temps real i mira quina posició ocupes.</p>
                    </div>
                    <a href="<?php echo get_permalink($id_ranking); ?>" class="ejb-btn ejb-btn-glass">
                        Veure Ranking <i data-lucide="arrow-right"></i>
                    </a>
                </div>
            </div>
        </section>
    </div>

    <style>
    @import url('https://fonts.cdnfonts.com/css/geomanist');

    :root {
        --ejb-primary: #cc0000;
        --ejb-primary-dark: #9f0d0d;
        --ejb-accent: #ff7c4e;
        --ejb-text-dark: #1a1a1a;
        --ejb-text-light: #666666;
        --ejb-white: #ffffff;
        --ejb-shadow: 0 20px 40px rgba(204, 0, 0, 0.15);
        --ejb-glass: rgba(255, 255, 255, 0.85);
        --ejb-radius: 32px;
    }

    .ejb-home-wrapper {
        font-family: 'Geomanist', sans-serif;
        color: var(--ejb-text-dark);
        overflow: hidden;
        margin-top: -30px; /* Compensa espaciado de Elementor si es necesario */
    }

    /* Hero Section */
    .ejb-hero {
        position: relative;
        min-height: 80vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 80px 30px;
        background: url('<?php echo esc_url($hero_image_url); ?>') center/cover no-repeat;
        border-radius: var(--ejb-radius);
        margin: 0 10px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,0.1);
    }

    .ejb-hero-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(0,0,0,0.5) 0%, rgba(204, 0, 0, 0.2) 100%);
        z-index: 1;
    }

    .ejb-hero-content {
        position: relative;
        z-index: 2;
        text-align: center;
        max-width: 850px;
        color: var(--ejb-white);
        animation: fadeInUp 1s cubic-bezier(0.23, 1, 0.32, 1);
    }

    .ejb-badge {
        display: inline-block;
        padding: 10px 22px;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 99px;
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 28px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
    }

    .ejb-hero-title {
        font-size: clamp(2.8rem, 9vw, 5rem);
        font-weight: 900;
        line-height: 1.05;
        margin-bottom: 28px;
        letter-spacing: -0.02em;
    }

    .ejb-hero-title span {
        color: var(--ejb-accent);
        background: linear-gradient(90deg, #ff7c4e, #ffa782);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .ejb-hero-subtitle {
        font-size: 1.25rem;
        margin-bottom: 48px;
        opacity: 0.95;
        max-width: 650px;
        margin-left: auto;
        margin-right: auto;
        line-height: 1.6;
    }

    .ejb-hero-actions {
        display: flex;
        gap: 24px;
        justify-content: center;
        flex-wrap: wrap;
    }

    /* Buttons */
    .ejb-btn {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        padding: 18px 40px;
        border-radius: 99px;
        font-weight: 700;
        font-size: 1.15rem;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        text-decoration: none !important;
        cursor: pointer;
    }

    .ejb-btn-primary {
        background: linear-gradient(135deg, var(--ejb-primary) 0%, #e63535 100%);
        color: white !important;
        box-shadow: 0 15px 35px rgba(204, 0, 0, 0.3);
        border: none;
    }

    .ejb-btn-primary:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 45px rgba(204, 0, 0, 0.4);
        filter: brightness(1.1);
    }

    .ejb-btn-outline {
        background: rgba(255, 255, 255, 0.1);
        color: white !important;
        border: 2px solid rgba(255,255,255,0.7);
        backdrop-filter: blur(8px);
    }

    .ejb-btn-outline:hover {
        background: white;
        color: var(--ejb-primary) !important;
        transform: translateY(-6px);
        border-color: white;
    }

    /* Features Section */
    .ejb-features {
        padding: 120px 30px;
        text-align: center;
        background: #fafafa;
    }

    .ejb-section-header {
        margin-bottom: 80px;
    }

    .ejb-section-title {
        font-size: 3rem;
        font-weight: 800;
        margin-bottom: 20px;
        letter-spacing: -0.01em;
    }

    .ejb-section-divider {
        width: 80px;
        height: 6px;
        background: linear-gradient(90deg, var(--ejb-primary) 0%, var(--ejb-accent) 100%);
        margin: 0 auto;
        border-radius: 3px;
    }

    .ejb-features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 40px;
        max-width: 1300px;
        margin: 0 auto;
    }

    .ejb-feature-card {
        background: var(--ejb-white);
        padding: 50px 40px;
        border-radius: var(--ejb-radius);
        box-shadow: 0 15px 40px rgba(0,0,0,0.04);
        transition: all 0.4s ease;
        border: 1px solid #f2f2f2;
        position: relative;
    }

    .ejb-feature-card:hover {
        transform: translateY(-12px);
        box-shadow: 0 30px 60px rgba(0,0,0,0.08);
        border-color: rgba(204, 0, 0, 0.15);
    }

    .ejb-icon-wrapper {
        width: 90px;
        height: 90px;
        background: #fff1f1;
        color: var(--ejb-primary);
        border-radius: 26px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 32px;
        transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .ejb-feature-card:hover .ejb-icon-wrapper {
        background: var(--ejb-primary);
        color: white;
        transform: scale(1.1) rotate(-8deg);
    }

    .ejb-icon-wrapper i {
        width: 44px;
        height: 44px;
    }

    .ejb-feature-card h3 {
        font-size: 1.75rem;
        margin-bottom: 20px;
        font-weight: 800;
    }

    .ejb-feature-card p {
        color: var(--ejb-text-light);
        line-height: 1.7;
        font-size: 1.1rem;
    }

    /* Ranking CTA */
    .ejb-ranking-cta {
        padding: 50px 30px 120px;
        max-width: 1300px;
        margin: 0 auto;
    }

    .ejb-glass-card {
        background: linear-gradient(135deg, #cc0000 0%, #ff4d4d 100%);
        border-radius: var(--ejb-radius);
        padding: 60px;
        position: relative;
        overflow: hidden;
        color: white;
        box-shadow: 0 40px 80px rgba(204, 0, 0, 0.25);
    }

    .ejb-glass-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 40px;
        position: relative;
        z-index: 2;
    }

    .ejb-glass-text h3 {
        font-size: 2.2rem;
        font-weight: 850;
        margin-bottom: 12px;
        letter-spacing: -0.01em;
    }

    .ejb-glass-text p {
        font-size: 1.2rem;
        opacity: 0.95;
    }

    .ejb-btn-glass {
        background: white;
        color: var(--ejb-primary) !important;
        padding: 20px 48px;
        font-size: 1.2rem;
    }

    .ejb-btn-glass:hover {
        background: #ffffff;
        transform: scale(1.05) translate(10px, -2px);
    }

    /* Animations */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(40px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Floating shapes */
    .ejb-floating-shapes .shape {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
        z-index: 1;
        pointer-events: none;
    }

    .shape-1 { width: 400px; height: 400px; top: -150px; right: -150px; animation: float 12s infinite alternate; }
    .shape-2 { width: 250px; height: 250px; bottom: -80px; left: -80px; animation: float 10s infinite alternate-reverse; }
    .shape-3 { width: 150px; height: 150px; top: 10%; left: 10%; background: radial-gradient(circle, rgba(255,255,255,0.15), transparent); animation: float 15s infinite linear; }

    @keyframes float {
        0% { transform: translate(0, 0) rotate(0deg); }
        50% { transform: translate(20px, -30px) rotate(10deg); }
        100% { transform: translate(-10px, -50px) rotate(20deg); }
    }

    @media (max-width: 768px) {
        .ejb-hero-title { font-size: 3.2rem; }
        .ejb-glass-content { justify-content: center; text-align: center; }
        .ejb-btn-glass { width: 100%; justify-content: center; }
        .ejb-hero { min-height: 70vh; padding: 60px 24px; }
        .ejb-section-title { font-size: 2.5rem; }
    }
    </style>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('ejb_home_page', 'ejb_landing_page_shortcode');
