<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';

$portal_api_url = 'http://localhost/bymonday/portal/api/menu_items.php';

$response = @file_get_contents($portal_api_url);
$menu_data = [];

if ($response !== false) {
    $data = json_decode($response, true);
    if (!empty($data['success']) && !empty($data['items'])) {
        foreach ($data['items'] as $product) {
            $product['category_name'] = $product['category'];
            $product['has_sizes']     = '0';
            $product['image']         = null;

            if (!empty($product['image_url'])) {
                $product['image_full_url'] = 'http://localhost/bymonday/portal' . $product['image_url'];
            } else {
                $product['image_full_url'] = null;
            }

            $category = $product['category'] ?? 'Other';
            if (!isset($menu_data[$category])) $menu_data[$category] = [];
            $menu_data[$category][] = $product;
        }
    }
}

// Load favorites for logged-in customer
$favorite_ids = [];
if (isLoggedIn() && $_SESSION['role'] == 'customer') {
    $fav_stmt = $conn->prepare("SELECT menu_item_id FROM favorites WHERE user_id = ?");
    $fav_stmt->bind_param("i", $_SESSION['user_id']);
    $fav_stmt->execute();
    $fav_result = $fav_stmt->get_result();
    while ($row = $fav_result->fetch_assoc()) {
        $favorite_ids[] = $row['menu_item_id'];
    }
    $fav_stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Menu — Coffee by Monday Mornings</title>
    <meta name="description" content="Browse our premium coffee menu.">
    <meta name="theme-color" content="#1a0f08">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style-menu.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div id="scrollProgress"></div>

    <!-- ═══ Navbar ═══ -->
    <nav class="navbar" id="navbar">
        <a href="index.php" class="navbar-logo">
            <img src="<?= BASE_URL ?>/assets/images/logo1.png" alt="Coffee by Monday Mornings">
        </a>

        <!-- Hamburger  -->
        <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <!-- Nav links & cart -->
        <div class="navbar-links" id="navLinks">
            <a href="index.php" class="nav-link">Home</a>
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
        </div>

        <!-- Cart always in top bar -->
        <?php if (isLoggedIn() && $_SESSION['role'] == 'customer'): ?>
        <a href="favorites.php" class="fav-btn" aria-label="My Favorites">
            <i class="fas fa-heart"></i>
            <span class="fav-badge" id="favCount"><?= count($favorite_ids) ?></span>
        </a>
        <?php endif; ?>

        <button class="cart-btn" onclick="openCart()" aria-label="View cart">
            <i class="fas fa-shopping-bag"></i>
            <span class="cart-badge" id="cartCount">0</span>
        </button>
    </nav>

    <!-- ═══ Hero ═══ -->
    <section class="hero">
        <div class="hero-eyebrow"><i class="fas fa-mug-hot"></i>&nbsp; Freshly Crafted</div>
        <h1>Our <em>Menu</em></h1>
        <p class="hero-sub">Discover carefully crafted coffee drinks and treats, made to order — every time.</p>
    </section>

    <!-- ═══ Filter bar ═══ -->
    <div class="filter-bar">
        <div class="filter-inner">
            <button class="search-toggle" id="searchToggle" onclick="toggleSearch()" aria-label="Search">
                <i class="fas fa-search"></i>
            </button>
            <div class="search-wrap" id="searchWrap">
                <i class="fas fa-search s-icon"></i>
                <input type="search" id="searchInput" placeholder="Search coffee…" oninput="handleSearch(this)" autocomplete="off">
                <button class="search-clear" id="searchClear" onclick="clearSearch()" aria-label="Clear"><i class="fas fa-times"></i></button>
            </div>
            <div class="tabs-wrap">
                <div class="tabs">
                    <button class="tab active" data-cat="all" onclick="filterCat('all',this)">All Items</button>
                    <?php foreach (array_keys($menu_data) as $cat): ?>
                        <button class="tab" data-cat="<?= htmlspecialchars($cat) ?>"
                                onclick="filterCat('<?= htmlspecialchars($cat) ?>',this)">
                            <?= htmlspecialchars($cat) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ Products ═══ -->
    <section class="products-section">
        <h2 class="section-label" id="sectionLabel">For You</h2>
        <p class="section-sub" id="sectionSub">All items available today</p>
        <div class="products-grid" id="productsGrid">
            <?php foreach ($menu_data as $category => $products): ?>
                <?php foreach ($products as $product): ?>
                    <article class="product-card"
                             data-id="<?= $product['id'] ?>"
                             data-cat="<?= htmlspecialchars($category) ?>"
                             data-search="<?= strtolower(htmlspecialchars($product['name'].' '.$product['description'])) ?>"
                             onclick='openProduct(<?= json_encode($product) ?>)'>
                        <div class="card-stripe"></div>
                        <div class="card-img-wrap">
                            <img class="card-img"
                                src="<?= !empty($product['image_full_url']) ? htmlspecialchars($product['image_full_url']) : BASE_URL . '/assets/images/placeholder.jpg' ?>"
                                alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy">
                            <span class="card-category"><?= htmlspecialchars($category) ?></span>
                            <?php if (isLoggedIn() && $_SESSION['role'] == 'customer'): ?>
                                <button class="card-fav <?= in_array($product['id'], $favorite_ids) ? 'active' : '' ?>"
                                        onclick="event.stopPropagation();toggleFavorite(this, <?= $product['id'] ?>)"
                                        aria-label="Favorite">
                                    <i class="fas fa-heart"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h3 class="card-name"><?= htmlspecialchars($product['name']) ?></h3>
                            <p class="card-desc"><?= htmlspecialchars($product['description']) ?></p>
                            <div class="card-footer">
                                <div class="card-price">
                                    ₱<?= number_format($product['price'],2) ?>
                                    <?php if($product['has_sizes']=='1'): ?><small>from</small><?php endif; ?>
                                </div>
                                <?php if ($product['actually_available']): ?>
                                    <button class="card-add"
                                            onclick="event.stopPropagation();openProduct(<?= htmlspecialchars(json_encode($product)) ?>)"
                                            aria-label="Add to cart">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                <?php else: ?>
                                    <button class="card-add card-unavailable" disabled aria-label="Out of stock">
                                        <i class="fas fa-times"></i> Out of Stock
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ═══ Product Modal ═══ -->
    <div id="productModal" class="modal-overlay" role="dialog" aria-modal="true">
        <div class="modal-box">
            <button class="modal-close" onclick="closeProduct()" aria-label="Close"><i class="fas fa-times"></i></button>
            <div class="modal-inner">
                <div class="modal-img-col">
                    <img id="modalImg" src="" alt="" class="modal-img">
                </div>
                <div class="modal-details">
                    <p class="modal-cat" id="modalCat"></p>
                    <h2 class="modal-name" id="modalName"></h2>
                    <p class="modal-desc" id="modalDesc"></p>
                    <div class="modal-price" id="modalPrice">₱0.00</div>

                    <div id="sizeSection" class="form-group" style="display:none;">
                        <label>Choose Size</label>
                        <div class="size-grid">
                            <div>
                                <input type="radio" name="psize" id="sz-s" value="s" class="size-opt" onchange="updatePrice()">
                                <label for="sz-s" class="size-label">S<span id="priceS"></span></label>
                            </div>
                            <div>
                                <input type="radio" name="psize" id="sz-m" value="m" class="size-opt" checked onchange="updatePrice()">
                                <label for="sz-m" class="size-label">M<span id="priceM"></span></label>
                            </div>
                            <div>
                                <input type="radio" name="psize" id="sz-l" value="l" class="size-opt" onchange="updatePrice()">
                                <label for="sz-l" class="size-label">L<span id="priceL"></span></label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Quantity</label>
                        <div class="qty-row">
                            <button class="qty-btn" onclick="changeQty(-1)">−</button>
                            <div class="qty-val" id="qtyVal">1</div>
                            <button class="qty-btn" onclick="changeQty(1)">+</button>
                        </div>
                    </div>

                    <div class="modal-actions">
                        <button class="btn btn-outline" onclick="addToCart()"><i class="fas fa-shopping-bag"></i> Add to Cart</button>
                        <button class="btn btn-primary" onclick="buyNow()"><i class="fas fa-bolt"></i> Buy Now</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ Cart Modal ═══ -->
    <div id="cartModal" class="cart-overlay" role="dialog" aria-modal="true">
        <div class="cart-box">
            <div class="cart-head">
                <h2><i class="fas fa-shopping-bag" style="color:var(--accent)"></i> Your Cart</h2>
                <button class="cart-close" onclick="closeCart()" aria-label="Close cart"><i class="fas fa-times"></i></button>
            </div>
            <div class="cart-items" id="cartItems">
                <div class="cart-empty"><i class="fas fa-shopping-bag"></i><p>Your cart is empty</p></div>
            </div>
            <div class="cart-foot">
                <div class="cart-total-row">
                    <span>Total</span>
                    <span class="cart-total-amount" id="cartTotal">₱0.00</span>
                </div>
                <button id="checkoutBtn" class="checkout-btn" onclick="checkout()" disabled>
                    Proceed to Checkout &nbsp;<i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"><i class="fas fa-check-circle"></i><span id="toastMsg"></span></div>
    <button id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Back to top"><i class="fas fa-arrow-up"></i></button>

<script>
let currentProduct=null, qty=1, cart=[];

window.addEventListener('DOMContentLoaded',()=>{
    cart=JSON.parse(localStorage.getItem('mmCart')||'[]');
    syncBadge();
});

/* scroll */
window.addEventListener('scroll',()=>{
    const prog=document.getElementById('scrollProgress');
    const bt=document.getElementById('backToTop');
    const pct=window.scrollY/(document.documentElement.scrollHeight-window.innerHeight)*100;
    prog.style.width=pct+'%';
    bt.style.display=window.scrollY>300?'flex':'none';
},{passive:true});

/* ── Mobile nav ── */
const navToggle=document.getElementById('navToggle');
const navLinks=document.getElementById('navLinks');

navToggle.addEventListener('click',()=>{
    const open=navLinks.classList.toggle('open');
    navToggle.classList.toggle('open',open);
    navToggle.setAttribute('aria-expanded',open);
    document.body.style.overflow=open?'hidden':'';
});

// close on outside click
document.addEventListener('click',e=>{
    if(!document.getElementById('navbar').contains(e.target)){
        navLinks.classList.remove('open');
        navToggle.classList.remove('open');
        navToggle.setAttribute('aria-expanded','false');
        document.body.style.overflow='';
    }
});

// close on link click (mobile)
navLinks.querySelectorAll('.nav-link').forEach(l=>l.addEventListener('click',()=>{
    navLinks.classList.remove('open');
    navToggle.classList.remove('open');
    navToggle.setAttribute('aria-expanded','false');
    document.body.style.overflow='';
}));

// close on resize to desktop
window.addEventListener('resize',()=>{
    if(window.innerWidth>700){
        navLinks.classList.remove('open');
        navToggle.classList.remove('open');
        document.body.style.overflow='';
    }
},{passive:true});

/* ── Search ── */
function toggleSearch(){
    const wrap=document.getElementById('searchWrap');
    const btn=document.getElementById('searchToggle');
    const inp=document.getElementById('searchInput');
    const open=wrap.classList.toggle('open');
    btn.classList.toggle('open',open);
    if(open)setTimeout(()=>inp.focus(),420);
    else clearSearch();
}
function handleSearch(inp){
    const val=inp.value.trim().toLowerCase();
    document.getElementById('searchClear').style.display=val?'block':'none';
    applyFilter(null,val);
}
function clearSearch(){
    document.getElementById('searchInput').value='';
    document.getElementById('searchClear').style.display='none';
    applyFilter(null,'');
}

/* ── Filter ── */
function filterCat(cat,el){
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    el.classList.add('active');
    el.scrollIntoView({behavior:'smooth',block:'nearest',inline:'center'});
    document.getElementById('sectionLabel').textContent=cat==='all'?'For You':cat;
    document.getElementById('sectionSub').textContent=cat==='all'?'All items available today':`Everything in ${cat}`;
    applyFilter(cat,document.getElementById('searchInput').value.trim().toLowerCase());
}
function applyFilter(cat,search){
    const activeCat=cat||document.querySelector('.tab.active')?.dataset.cat||'all';
    let visible=0;
    document.querySelectorAll('.product-card').forEach(card=>{
        const show=(activeCat==='all'||card.dataset.cat===activeCat)&&(!search||card.dataset.search.includes(search));
        card.style.display=show?'flex':'none';
        if(show)visible++;
    });
    let empty=document.getElementById('emptyState');
    if(visible===0){
        if(!empty){
            empty=document.createElement('div');empty.id='emptyState';empty.className='empty-state';
            empty.innerHTML='<i class="fas fa-mug-hot"></i><h3>Nothing found</h3><p>Try a different search or category</p>';
            document.getElementById('productsGrid').appendChild(empty);
        }
    } else if(empty) empty.remove();
}

/* ── Product modal ── */
function openProduct(product){
    currentProduct=product; qty=1;
    document.getElementById('modalImg').src='<?= BASE_URL ?>/assets/images/'+product.image;
    document.getElementById('modalImg').alt=product.name;
    document.getElementById('modalCat').textContent=product.category_name||'';
    document.getElementById('modalName').textContent=product.name;
    document.getElementById('modalDesc').textContent=product.description;
    document.getElementById('qtyVal').textContent=1;
    const sizeSec=document.getElementById('sizeSection');
    if(product.has_sizes=='1'){
        sizeSec.style.display='flex';
        document.getElementById('sz-m').checked=true;
        document.getElementById('priceS').textContent='₱'+parseFloat(product.size_small_price).toFixed(2);
        document.getElementById('priceM').textContent='₱'+parseFloat(product.size_medium_price).toFixed(2);
        document.getElementById('priceL').textContent='₱'+parseFloat(product.size_large_price).toFixed(2);
        document.getElementById('modalPrice').textContent='₱'+parseFloat(product.size_medium_price).toFixed(2);
    } else {
        sizeSec.style.display='none';
        document.getElementById('modalPrice').textContent='₱'+parseFloat(product.price).toFixed(2);
    }
    document.getElementById('productModal').classList.add('open');
    document.body.style.overflow='hidden';
}
function closeProduct(){document.getElementById('productModal').classList.remove('open');document.body.style.overflow='';}
function updatePrice(){
    if(!currentProduct||currentProduct.has_sizes!='1')return;
    const sz=document.querySelector('input[name="psize"]:checked').value;
    const map={s:currentProduct.size_small_price,m:currentProduct.size_medium_price,l:currentProduct.size_large_price};
    document.getElementById('modalPrice').textContent='₱'+parseFloat(map[sz]).toFixed(2);
}
function changeQty(d){qty=Math.max(1,qty+d);document.getElementById('qtyVal').textContent=qty;}

/* ── Cart ── */
function resolveItem(){
    let size=null,price=parseFloat(currentProduct.price);
    if(currentProduct.has_sizes=='1'){
        size=document.querySelector('input[name="psize"]:checked').value;
        const map={s:currentProduct.size_small_price,m:currentProduct.size_medium_price,l:currentProduct.size_large_price};
        price=parseFloat(map[size]);
    }
    return{id:currentProduct.id+(size?'-'+size:''),product_id:currentProduct.id,name:currentProduct.name,price,size,quantity:qty,image:currentProduct.image};
}
function addToCart(){
    const item=resolveItem();
    const idx=cart.findIndex(c=>c.id===item.id);
    if(idx>-1)cart[idx].quantity+=item.quantity; else cart.push(item);
    saveCart();showToast(`${item.name} added to cart!`);closeProduct();
}
function buyNow(){addToCart();setTimeout(openCart,300);}
function saveCart(){localStorage.setItem('mmCart',JSON.stringify(cart));syncBadge();}
function syncBadge(){document.getElementById('cartCount').textContent=cart.reduce((s,i)=>s+i.quantity,0);}

function openCart(){renderCart();document.getElementById('cartModal').classList.add('open');document.body.style.overflow='hidden';}
function closeCart(){document.getElementById('cartModal').classList.remove('open');document.body.style.overflow='';}
function renderCart(){
    const el=document.getElementById('cartItems');
    if(!cart.length){
        el.innerHTML='<div class="cart-empty"><i class="fas fa-shopping-bag"></i><p>Your cart is empty</p></div>';
        document.getElementById('cartTotal').textContent='₱0.00';
        document.getElementById('checkoutBtn').disabled=true;return;
    }
    let html='',total=0;
    cart.forEach((item,i)=>{
        const sub=item.price*item.quantity;total+=sub;
        const sz=item.size?`Size ${item.size.toUpperCase()} · `:'';
        html+=`<div class="cart-item">
            <img class="cart-item-thumb" src="<?= BASE_URL ?>/assets/images/${item.image}" alt="${item.name}" loading="lazy">
            <div class="cart-item-info">
                <div class="cart-item-name">${item.name}</div>
                <div class="cart-item-meta">${sz}₱${item.price.toFixed(2)} each</div>
            </div>
            <div class="cart-item-right">
                <span class="cart-item-price">₱${sub.toFixed(2)}</span>
                <div class="cart-qty-ctrl">
                    <button class="cqty-btn" onclick="cqty(${i},-1)">−</button>
                    <span class="cqty-val">${item.quantity}</span>
                    <button class="cqty-btn" onclick="cqty(${i},1)">+</button>
                </div>
            </div>
            <button class="cart-remove-btn" onclick="removeItem(${i})" aria-label="Remove"><i class="fas fa-trash-alt"></i></button>
        </div>`;
    });
    el.innerHTML=html;
    document.getElementById('cartTotal').textContent='₱'+total.toFixed(2);
    document.getElementById('checkoutBtn').disabled=false;
}
function cqty(idx,delta){cart[idx].quantity=Math.max(1,cart[idx].quantity+delta);saveCart();renderCart();}
function removeItem(idx){cart.splice(idx,1);saveCart();renderCart();}
function checkout(){
    if(!cart.length)return;
    <?php if(isLoggedIn()&&$_SESSION['role']=='customer'): ?>
        window.location.href='checkout.php';
    <?php else: ?>
        if(confirm('Please log in to checkout. Go to login?')){localStorage.setItem('checkout_after_login','1');window.location.href='customer_login.php';}
    <?php endif; ?>
}

let toastTimer;
function showToast(msg){
    clearTimeout(toastTimer);
    const t=document.getElementById('toast');
    document.getElementById('toastMsg').textContent=msg;
    t.classList.add('show');
    toastTimer=setTimeout(()=>t.classList.remove('show'),3000);
}

document.getElementById('productModal').addEventListener('click',e=>{if(e.target===e.currentTarget)closeProduct();});
document.getElementById('cartModal').addEventListener('click',e=>{if(e.target===e.currentTarget)closeCart();});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeProduct();closeCart();}});

document.querySelectorAll('.user-chip').forEach(chip=>{
    chip.addEventListener('click',function(e){
        if(window.innerWidth <= 700){
            this.parentElement.classList.toggle('open');
        }
    });
});

</script>

<script>
setInterval(function () {
    fetch('http://localhost/bymonday/portal/api/menu_items.php')
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.success) return;

            data.items.forEach(function (item) {
                var card = document.querySelector('.product-card[data-id="' + item.id + '"]');
                if (!card) return;

                var btn = card.querySelector('.card-add');
                if (!btn) return;

                if (item.actually_available) {
                    btn.disabled = false;
                    btn.classList.remove('card-unavailable');
                    btn.innerHTML = '<i class="fas fa-plus"></i> Add';
                    btn.onclick = function (e) {
                        e.stopPropagation();
                        openProduct(item);
                    };
                } else {
                    btn.disabled = true;
                    btn.classList.add('card-unavailable');
                    btn.innerHTML = '<i class="fas fa-times"></i> Out of Stock';
                    btn.onclick = null;
                }
            });
        })
        .catch(function () {});
}, 30000);

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

// Favorites
function toggleFavorite(btn, itemId) {
    <?php if (!isLoggedIn()): ?>
        if (confirm('Please login to save favorites. Go to login?')) {
            window.location.href = 'customer_login.php';
        }
        return;
    <?php endif; ?>

    fetch('api/toggle_favorite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ menu_item_id: itemId })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        const badge = document.getElementById('favCount');
        let count = parseInt(badge.textContent) || 0;
        if (data.action === 'added') {
            btn.classList.add('active');
            badge.textContent = count + 1;
        } else {
            btn.classList.remove('active');
            badge.textContent = Math.max(0, count - 1);
        }
    })
    .catch(() => {});
}
</script>
</body>
</html>