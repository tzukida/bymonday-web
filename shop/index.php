<!--index.php-->
<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

$customerLoggedIn = (
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'customer'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Coffee by Monday Mornings</title>
  <meta name="description" content="Experience the perfect blend of quality coffee and exceptional service.">
  <meta name="theme-color" content="#1a0f08">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <!-- Scroll Progress -->
  <div id="scrollProgress"></div>

  <!-- Navigation -->
  <nav class="navbar" id="navbar">
    <a href="#home" class="navbar-logo">
      <img src="<?= BASE_URL ?>/assets/images/logo1.png" alt="Coffee by Monday Mornings">
    </a>

    <!-- Hamburger -->
    <button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>

    <div class="navbar-links" id="navLinks">
      <a href="#home" class="nav-link">Home</a>
      <a href="#features" class="nav-link">Features</a>
      <a href="menu.php" class="nav-link">Menu</a>
<?php if (isLoggedIn() && $_SESSION['role'] == 'customer'): ?>
    <div class="user-dropdown">
        <div class="user-chip">
            <i class="fas fa-user-circle"></i>
            <?= htmlspecialchars($_SESSION['full_name']) ?>
            <i class="fas fa-chevron-down drop-icon"></i>
        </div>

        <div class="user-menu">
            <a href="orders.php" class="dropdown-link">
                <i class="fas fa-receipt"></i> My Orders
            </a>
            <a href="profile.php" class="dropdown-link">
                <i class="fas fa-user"></i> My Profile
            </a>
            <a href="logout.php" class="dropdown-link logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
<?php else: ?>
    <a href="customer_login.php" class="nav-link">Login</a>
<?php endif; ?>

      <button class="nav-btn" onclick="location.href='/bymonday/portal/index.php'">
        <i class="fas fa-th"></i> Portal
      </button>
    </div>
  </nav>

  <!-- Hero -->
  <section class="hero" id="home">
    <!-- decorative grain overlay -->
    <div class="hero-grain"></div>

    <div class="hero-container">
      <div class="hero-content">
        <div class="hero-badge">
          <span class="badge-dot"></span>
          Premium Quality Coffee
        </div>

        <h1 class="hero-title">
          Coffee by<br>
          <span class="hero-title-accent">Monday Mornings</span>
        </h1>

        <p class="hero-description">
          Every cup crafted with precision and passion — from first light
          to your last sip, we make mornings worth waking up for.
        </p>

        <div class="hero-buttons">
          <button class="hero-btn hero-btn-primary" onclick="location.href='menu.php'">
            <i class="fas fa-shopping-bag"></i> Order Now
          </button>
          <button class="hero-btn hero-btn-secondary" onclick="scrollToSection('#features')">
            Explore <i class="fas fa-arrow-down"></i>
          </button>
        </div>

        <!-- mini stats row -->
        <div class="hero-pills">
          <div class="pill"><i class="fas fa-star"></i> 4.9 Rating</div>
          <div class="pill-divider"></div>
          <div class="pill"><i class="fas fa-mug-hot"></i> 10k+ Orders</div>
          <div class="pill-divider"></div>
          <div class="pill"><i class="fas fa-heart"></i> 98% Happy</div>
        </div>
      </div>

      <div class="hero-visual">
        <div class="hero-card">
          <!-- floating label -->
          <div class="hero-card-label">
            <i class="fas fa-fire"></i> Trending Today
          </div>
          <div class="hero-image-wrapper">
            <div class="hero-image"></div>
            <!-- overlay shimmer -->
            <div class="hero-image-overlay"></div>
          </div>
          <div class="hero-stats">
            <div class="stat-item" data-counter="98" data-suffix="%">
              <span class="stat-number">0%</span>
              <span class="stat-label">Satisfaction</span>
            </div>
            <div class="stat-item" data-counter="10" data-suffix="k+">
              <span class="stat-number">0k+</span>
              <span class="stat-label">Orders</span>
            </div>
            <div class="stat-item" data-counter="4.9" data-suffix="★" data-decimal="1">
              <span class="stat-number">0★</span>
              <span class="stat-label">Rating</span>
            </div>
          </div>
        </div>

        <!-- floating decorative beans -->
        <div class="bean bean-1"><i class="fas fa-circle"></i></div>
        <div class="bean bean-2"><i class="fas fa-circle"></i></div>
      </div>
    </div>

    <!-- scroll hint -->
    <div class="scroll-hint">
      <span>Scroll</span>
      <div class="scroll-line"></div>
    </div>
  </section>

  <!-- Features -->
  <section class="features" id="features">
    <div class="features-container">
      <div class="section-header">
        <span class="section-eyebrow">Why Choose Us</span>
        <h2 class="section-title">Built for<br><em>Coffee Lovers</em></h2>
        <p class="section-subtitle">
          Modern technology meets artisanal crafting.
          Experience coffee ordering the way it should be.
        </p>
      </div>

      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon-wrap">
            <div class="feature-icon"><i class="fa-solid fa-mug-hot"></i></div>
          </div>
          <h3 class="feature-title">Premium Quality</h3>
          <p class="feature-text">Only the finest beans sourced from world-renowned farms. Every sip tells a story of craft.</p>
          <div class="feature-arrow"><i class="fas fa-arrow-right"></i></div>
        </div>

        <div class="feature-card">
          <div class="feature-icon-wrap">
            <div class="feature-icon"><i class="fas fa-bolt"></i></div>
          </div>
          <h3 class="feature-title">Lightning Fast</h3>
          <p class="feature-text">Quick ordering powered by our advanced POS system. Your coffee, without the wait.</p>
          <div class="feature-arrow"><i class="fas fa-arrow-right"></i></div>
        </div>

        <div class="feature-card">
          <div class="feature-icon-wrap">
            <div class="feature-icon"><i class="fa-solid fa-basket-shopping"></i></div>
          </div>
          <h3 class="feature-title">Seamless Ordering</h3>
          <p class="feature-text">Browse, customize, and checkout in seconds. Modern tech for modern coffee lovers.</p>
          <div class="feature-arrow"><i class="fas fa-arrow-right"></i></div>
        </div>

        <div class="feature-card">
          <div class="feature-icon-wrap">
            <div class="feature-icon"><i class="fas fa-heart"></i></div>
          </div>
          <h3 class="feature-title">Made with Love</h3>
          <p class="feature-text">Every drink prepared with care by our skilled baristas who genuinely love what they do.</p>
          <div class="feature-arrow"><i class="fas fa-arrow-right"></i></div>
        </div>

        <div class="feature-card">
          <div class="feature-icon-wrap">
            <div class="feature-icon"><i class="fa-solid fa-star"></i></div>
          </div>
          <h3 class="feature-title">Guaranteed Satisfaction</h3>
          <p class="feature-text">Your happiness is our priority. We exceed expectations with every single order.</p>
          <div class="feature-arrow"><i class="fas fa-arrow-right"></i></div>
        </div>

        <div class="feature-card feature-card-cta">
          <div class="feature-cta-inner">
            <p class="feature-cta-label">Ready to taste the difference?</p>
            <h3 class="feature-cta-title">Order Your<br>First Cup</h3>
            <button class="feature-cta-btn" onclick="location.href='menu.php'">
              View Menu <i class="fas fa-arrow-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Marquee strip -->
  <div class="marquee-strip" aria-hidden="true">
    <div class="marquee-track">
      <span>Espresso</span><span class="dot">·</span>
      <span>Cold Brew</span><span class="dot">·</span>
      <span>Latte</span><span class="dot">·</span>
      <span>Cappuccino</span><span class="dot">·</span>
      <span>Americano</span><span class="dot">·</span>
      <span>Macchiato</span><span class="dot">·</span>
      <span>Flat White</span><span class="dot">·</span>
      <span>Iced Coffee</span><span class="dot">·</span>
      <span>Espresso</span><span class="dot">·</span>
      <span>Cold Brew</span><span class="dot">·</span>
      <span>Latte</span><span class="dot">·</span>
      <span>Cappuccino</span><span class="dot">·</span>
      <span>Americano</span><span class="dot">·</span>
      <span>Macchiato</span><span class="dot">·</span>
      <span>Flat White</span><span class="dot">·</span>
      <span>Iced Coffee</span><span class="dot">·</span>
    </div>
  </div>

  <!-- CTA -->
  <section class="cta">
    <div class="cta-bg-text" aria-hidden="true">Coffee</div>
    <div class="cta-container">
      <div class="cta-eyebrow"><i class="fas fa-mug-hot"></i> Ready to Order?</div>
      <h2 class="cta-title">Your Perfect Cup<br><em>Awaits</em></h2>
      <p class="cta-text">
        Discover our full menu and place your order today.
        Every cup is a new experience crafted just for you.
      </p>
      <button class="cta-button" onclick="location.href='menu.php'">
        Browse Menu <i class="fas fa-arrow-right"></i>
      </button>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-container">
      <div class="footer-grid">
        <div class="footer-about">
          <div class="footer-brand">
            <img src="<?= BASE_URL ?>/assets/images/logo1.png" alt="Logo">
            <span class="footer-brand-text">Monday Mornings</span>
          </div>
          <p class="footer-description">
            Crafting exceptional coffee experiences since 2015.
            Every cup tells a story, every sip brings joy.
          </p>
          <div class="footer-social">
            <a href="#" class="social-icon" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="#" class="social-icon" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="#" class="social-icon" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
          </div>
        </div>

        <div class="footer-column">
          <h4>Navigate</h4>
          <ul class="footer-links">
            <li><a href="#home"><i class="fas fa-chevron-right"></i>Home</a></li>
            <li><a href="menu.php"><i class="fas fa-chevron-right"></i>Menu</a></li>
            <li><a href="#features"><i class="fas fa-chevron-right"></i>Features</a></li>
            <li><a href="#"><i class="fas fa-chevron-right"></i>About Us</a></li>
          </ul>
        </div>

        <div class="footer-column">
          <h4>Support</h4>
          <ul class="footer-links">
            <li><a href="#"><i class="fas fa-chevron-right"></i>Help Center</a></li>
            <li><a href="#"><i class="fas fa-chevron-right"></i>Contact Us</a></li>
            <li><a href="#"><i class="fas fa-chevron-right"></i>FAQs</a></li>
            <li><a href="#"><i class="fas fa-chevron-right"></i>Feedback</a></li>
          </ul>
        </div>

        <div class="footer-column">
          <h4>Contact</h4>
          <ul class="footer-contact">
            <li><i class="fas fa-map-marker-alt"></i> <a href="https://www.bing.com/maps/search?v=2&pc=FACEBK&mid=8100&mkt=en-US&fbclid=IwY2xjawQcqitleHRuA2FlbQIxMABicmlkETFVMUI3N2dMRjBDV0lwZjBTc3J0YwZhcHBfaWQQMjIyMDM5MTc4ODIwMDg5MgABHrFJAEkiPrScn3ITudOMHxp-C1jTujiDqhpqw6FUFv14ZoRnYaFgm8JwZTJY_aem_dhM3Vl_zcRuct6qmQhJs2w&FORM=FBKPL1&q=Unit+E+853+M.+Naval+Street%2C+Sipac-Almacen+%28Beside+LBC+M.+Naval%29%2C+Navotas%2C+Philippines&cp=14.657288%7E120.947695&lvl=16&style=r" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;">Philippines, Metro Manila · Navotas</a></li>
            <li><i class="fas fa-envelope"></i> hello@mondaymornings.ph</li>
            <li><i class="fas fa-phone"></i> +63 912 345 6789</li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <div class="footer-bottom-left">&copy; 2026 Coffee by Monday Mornings. All rights reserved.</div>
        <div class="footer-links-inline">
          <a href="#">Privacy</a>
          <a href="#">Terms</a>
          <a href="#">Cookies</a>
        </div>
      </div>
    </div>
  </footer>

  <button id="backToTop" aria-label="Back to top"><i class="fas fa-arrow-up"></i></button>

  <script>
    /* ── Scroll progress ── */
    window.addEventListener('scroll', () => {
      const prog = document.getElementById('scrollProgress');
      const pct = window.scrollY / (document.documentElement.scrollHeight - window.innerHeight) * 100;
      prog.style.width = pct + '%';

      /* navbar */
      document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 60);

      /* back to top */
      const bt = document.getElementById('backToTop');
      bt.classList.toggle('visible', window.scrollY > 300);
    }, {passive:true});

    /* ── Mobile nav ── */
    const menuToggle = document.getElementById('menuToggle');
    const navLinks   = document.getElementById('navLinks');

    menuToggle.addEventListener('click', () => {
      const open = navLinks.classList.toggle('active');
      menuToggle.classList.toggle('active', open);
      menuToggle.setAttribute('aria-expanded', open);
      document.body.style.overflow = open ? 'hidden' : '';
    });

    document.addEventListener('click', e => {
      if (!document.getElementById('navbar').contains(e.target)) {
        navLinks.classList.remove('active');
        menuToggle.classList.remove('active');
        menuToggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      }
    });

    navLinks.querySelectorAll('.nav-link').forEach(l => l.addEventListener('click', () => {
      navLinks.classList.remove('active');
      menuToggle.classList.remove('active');
      menuToggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }));

    window.addEventListener('resize', () => {
      if (window.innerWidth > 768) {
        navLinks.classList.remove('active');
        menuToggle.classList.remove('active');
        document.body.style.overflow = '';
      }
    }, {passive:true});

    /* ── Smooth scroll ── */
    function scrollToSection(target) {
      const el = document.querySelector(target);
      if (el) window.scrollTo({top: el.offsetTop - 68, behavior:'smooth'});
    }
    document.querySelectorAll('a[href^="#"]').forEach(a => {
      a.addEventListener('click', e => {
        const href = a.getAttribute('href');
        if (href && href !== '#') { e.preventDefault(); scrollToSection(href); }
      });
    });

    /* ── Counter animation ── */
    function animateCounter(el) {
      const target   = parseFloat(el.dataset.counter);
      const suffix   = el.dataset.suffix || '';
      const decimal  = parseInt(el.dataset.decimal || '0');
      const numEl    = el.querySelector('.stat-number');
      const duration = 1800;
      const steps    = 60;
      const step     = target / steps;
      let current    = 0, count = 0;
      const timer = setInterval(() => {
        current += step; count++;
        if (count >= steps) { current = target; clearInterval(timer); }
        numEl.textContent = (decimal ? current.toFixed(decimal) : Math.round(current)) + suffix;
      }, duration / steps);
    }

    /* ── Intersection observer ── */
    const io = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('in-view');
        if (entry.target.dataset.counter && !entry.target.dataset.counted) {
          entry.target.dataset.counted = '1';
          animateCounter(entry.target);
        }
        io.unobserve(entry.target);
      });
    }, { threshold: 0.18 });

    document.querySelectorAll('.feature-card, .stat-item, .section-header').forEach(el => io.observe(el));

    /* ── Parallax (subtle) ── */
    window.addEventListener('scroll', () => {
      const s = window.scrollY;
      const hc = document.querySelector('.hero-content');
      const hv = document.querySelector('.hero-visual');
      if (hc && s < 700) hc.style.transform = `translateY(${s * 0.15}px)`;
      if (hv && s < 700) hv.style.transform = `translateY(${s * 0.08}px)`;
    }, {passive:true});

    /* ── Back to top ── */
    document.getElementById('backToTop').addEventListener('click', () =>
      window.scrollTo({top:0,behavior:'smooth'})
    );

    document.addEventListener('DOMContentLoaded', function(){

      const dropdown = document.querySelector('.user-dropdown');
      if(!dropdown) return;

      const chip = dropdown.querySelector('.user-chip');

      chip.addEventListener('click', function(e){
        e.stopPropagation();
        dropdown.classList.toggle('active');
      });

      document.addEventListener('click', function(){
        dropdown.classList.remove('active');
      });

    });
    
    // User dropdown — toggle on click
    const userChip = document.querySelector('.user-chip');
    const userDropdown = document.querySelector('.user-dropdown');

    if (userChip && userDropdown) {
        userChip.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('open');
        });

        document.addEventListener('click', function(e) {
            if (!userDropdown.contains(e.target)) {
                userDropdown.classList.remove('open');
            }
        });
    }
  </script>
</body>
</html>