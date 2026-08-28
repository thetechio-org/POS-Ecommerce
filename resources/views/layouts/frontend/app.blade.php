<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $setting->business_name ?? 'Online Store' }}</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries -->
    <link href="{{ asset('build/assets/frontend/lib/lightbox/css/lightbox.min.css') }}" rel="stylesheet">
    <link href="{{ asset('build/assets/frontend/lib/owlcarousel/assets/owl.carousel.min.css') }}" rel="stylesheet">
    <link href="{{ asset('build/assets/frontend/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('build/assets/frontend/css/style.css') }}" rel="stylesheet">

    <style>
        :root {
            --clr-primary: {{ $setting->primary_color ?? '#2563eb' }};
            --clr-dark:    #0f172a;
            --clr-slate:   #1e293b;
            --clr-muted:   #64748b;
            --clr-light:   #f8fafc;
            --clr-border:  #e2e8f0;
            --radius-card: 14px;
            --shadow-sm:   0 1px 4px rgba(0,0,0,.07);
            --shadow-md:   0 6px 24px rgba(0,0,0,.1);
        }

        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; color: var(--clr-slate); background: #fff; }

        /* ── Topbar ── */
        .store-topbar {
            background: var(--clr-dark);
            padding: 7px 0;
            font-size: .78rem;
        }
        .store-topbar a { color: #94a3b8; text-decoration: none; transition: color .2s; }
        .store-topbar a:hover { color: #fff; }
        .topbar-social a {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; border-radius: 50%;
            background: rgba(255,255,255,.07); color: #94a3b8;
            font-size: .7rem; text-decoration: none; transition: all .2s;
        }
        .topbar-social a:hover { background: var(--clr-primary); color: #fff; }

        /* ── Navbar ── */
        .store-navbar {
            background: #fff;
            border-bottom: 1px solid var(--clr-border);
            box-shadow: var(--shadow-sm);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 1030;
        }
        .store-navbar .container { height: 64px; }
        .navbar-brand-name {
            font-size: 1.35rem; font-weight: 800;
            color: var(--clr-primary) !important;
            letter-spacing: -.5px; text-decoration: none;
        }
        .nav-pill-link {
            font-size: .875rem; font-weight: 500; color: var(--clr-muted) !important;
            padding: .4rem .9rem !important; border-radius: 8px;
            transition: all .2s; text-decoration: none;
        }
        .nav-pill-link:hover { color: var(--clr-primary) !important; background: rgba(37,99,235,.06); }
        .nav-pill-link.active { color: var(--clr-primary) !important; background: rgba(37,99,235,.1); font-weight: 600; }
        .cart-btn {
            position: relative; width: 40px; height: 40px; border-radius: 10px;
            background: var(--clr-light); border: 1px solid var(--clr-border);
            display: flex; align-items: center; justify-content: center;
            color: var(--clr-slate); text-decoration: none; transition: all .2s;
        }
        .cart-btn:hover { background: var(--clr-primary); color: #fff; border-color: var(--clr-primary); }
        .cart-badge {
            position: absolute; top: -6px; right: -6px;
            background: var(--clr-primary); color: #fff;
            font-size: .6rem; font-weight: 700;
            width: 17px; height: 17px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid #fff;
        }
        .user-avatar {
            width: 36px; height: 36px; border-radius: 10px;
            background: var(--clr-primary); color: #fff;
            font-size: .8rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }
        .dropdown-menu {
            border: 1px solid var(--clr-border);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            padding: .5rem;
            min-width: 180px;
        }
        .dropdown-item {
            border-radius: 8px; font-size: .875rem;
            padding: .5rem .75rem; font-weight: 500;
            color: var(--clr-slate);
        }
        .dropdown-item:hover { background: var(--clr-light); color: var(--clr-primary); }
        .dropdown-item.text-danger:hover { background: #fff1f2; color: #ef4444; }

        /* ── Page header ── */
        .page-header-band {
            background: linear-gradient(135deg, var(--clr-dark) 0%, var(--clr-slate) 100%);
            padding: 52px 0 36px;
        }
        .page-header-band h1 { font-size: 1.75rem; font-weight: 700; color: #fff; margin: 0 0 .5rem; }
        .breadcrumb-item a { color: #94a3b8; text-decoration: none; font-size: .82rem; }
        .breadcrumb-item.active { color: #e2e8f0; font-size: .82rem; }
        .breadcrumb-item + .breadcrumb-item::before { color: #475569; }

        /* ── Product card ── */
        .p-card {
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-card);
            overflow: hidden;
            transition: transform .22s ease, box-shadow .22s ease;
            background: #fff;
            display: flex; flex-direction: column; height: 100%;
        }
        .p-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
        .p-card-img {
            height: 210px; overflow: hidden;
            position: relative; background: var(--clr-light);
        }
        .p-card-img img {
            width: 100%; height: 100%; object-fit: cover;
            transition: transform .3s ease;
        }
        .p-card:hover .p-card-img img { transform: scale(1.04); }
        .p-card-body { padding: 1.1rem; display: flex; flex-direction: column; flex: 1; }
        .p-card-cat {
            position: absolute; top: 10px; left: 10px;
            background: rgba(15,23,42,.65); color: #fff;
            padding: 3px 10px; border-radius: 20px;
            font-size: .7rem; font-weight: 600; z-index: 2;
            backdrop-filter: blur(4px);
        }
        .p-card-sale {
            position: absolute; top: 10px; right: 10px;
            background: #ef4444; color: #fff;
            padding: 3px 8px; border-radius: 6px;
            font-size: .7rem; font-weight: 700; z-index: 2;
        }
        .p-card-oos {
            position: absolute; bottom: 0; left: 0; right: 0;
            background: rgba(15,23,42,.7); color: #fff;
            text-align: center; padding: 6px;
            font-size: .75rem; font-weight: 600; z-index: 2;
        }
        .p-card-title { font-size: .9rem; font-weight: 600; color: var(--clr-dark); margin-bottom: .2rem; line-height: 1.4; }
        .p-card-desc  { font-size: .78rem; color: #94a3b8; flex: 1; margin-bottom: .75rem; }
        .p-price      { font-size: 1.05rem; font-weight: 700; color: var(--clr-dark); }
        .p-price-orig { font-size: .8rem; color: #94a3b8; text-decoration: line-through; }
        .p-unit       { font-size: .72rem; color: #94a3b8; }

        /* ── Buttons ── */
        .btn-prim {
            background: var(--clr-primary); color: #fff;
            border: none; border-radius: 9px;
            padding: 9px 18px; font-size: .85rem; font-weight: 600;
            transition: all .2s; cursor: pointer; display: inline-flex;
            align-items: center; gap: 6px;
        }
        .btn-prim:hover { filter: brightness(1.1); color: #fff; transform: translateY(-1px); }
        .btn-outline {
            background: transparent; color: var(--clr-primary);
            border: 1.5px solid var(--clr-primary); border-radius: 9px;
            padding: 9px 18px; font-size: .85rem; font-weight: 600;
            transition: all .2s; cursor: pointer; display: inline-flex;
            align-items: center; gap: 6px; text-decoration: none;
        }
        .btn-outline:hover { background: var(--clr-primary); color: #fff; }
        .btn-ghost {
            background: var(--clr-light); color: var(--clr-muted);
            border: 1px solid var(--clr-border); border-radius: 9px;
            padding: 9px 18px; font-size: .85rem; font-weight: 500;
        }

        /* ── Feature cards ── */
        .feat-card {
            background: #fff; border: 1px solid var(--clr-border);
            border-radius: var(--radius-card); padding: 1.75rem 1.25rem;
            text-align: center; transition: box-shadow .2s;
        }
        .feat-card:hover { box-shadow: var(--shadow-md); }
        .feat-icon {
            width: 56px; height: 56px; border-radius: 14px;
            background: var(--clr-primary);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
        }
        .feat-icon i { color: #fff; font-size: 1.2rem; }
        .feat-card h6 { font-weight: 700; font-size: .9rem; color: var(--clr-dark); margin-bottom: .2rem; }
        .feat-card p  { font-size: .78rem; color: var(--clr-muted); margin: 0; }

        /* ── Category sidebar ── */
        .cat-link {
            display: flex; justify-content: space-between; align-items: center;
            padding: 8px 12px; border-radius: 9px;
            color: var(--clr-muted); font-size: .855rem;
            text-decoration: none; transition: all .2s;
            margin-bottom: 3px; font-weight: 500;
        }
        .cat-link:hover, .cat-link.active {
            background: rgba(37,99,235,.08);
            color: var(--clr-primary);
        }
        .cat-count {
            font-size: .7rem; background: var(--clr-light);
            color: var(--clr-muted); padding: 2px 8px; border-radius: 10px;
        }
        .cat-link.active .cat-count { background: var(--clr-primary); color: #fff; }

        /* ── Pagination ── */
        .pagination {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: wrap;
            list-style: none;
            margin: 0; padding: 0;
        }
        .pagination .page-item { display: inline-flex; }
        .pagination .page-link {
            border: 1.5px solid var(--clr-border); border-radius: 8px !important;
            color: var(--clr-muted); margin: 0 2px;
            font-size: .85rem; font-weight: 500;
        }
        .pagination .page-item.active .page-link {
            background: var(--clr-primary);
            border-color: var(--clr-primary); color: #fff;
        }
        .pagination .page-link:hover { border-color: var(--clr-primary); color: var(--clr-primary); }

        /* ── Inputs ── */
        .field-input {
            border: 1.5px solid var(--clr-border); border-radius: 9px;
            padding: 10px 14px; font-size: .875rem; color: var(--clr-slate);
            width: 100%; transition: border-color .2s, box-shadow .2s;
        }
        .field-input:focus {
            border-color: var(--clr-primary); outline: none;
            box-shadow: 0 0 0 3px rgba(37,99,235,.1);
        }

        /* ── Footer ── */
        .store-footer { background: var(--clr-dark); }
        .footer-brand { font-size: 1.25rem; font-weight: 800; color: #fff; }
        .footer-about { color: #64748b; font-size: .82rem; line-height: 1.6; }
        .footer-h { color: #e2e8f0; font-weight: 600; font-size: .78rem; text-transform: uppercase; letter-spacing: .8px; margin-bottom: 1rem; }
        .footer-a { color: #64748b; font-size: .84rem; text-decoration: none; display: block; margin-bottom: .55rem; transition: color .2s; }
        .footer-a:hover { color: #fff; }
        .footer-social a {
            display: inline-flex; align-items: center; justify-content: center;
            width: 34px; height: 34px; border-radius: 9px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
            color: #64748b; font-size: .8rem; text-decoration: none;
            transition: all .2s; margin-right: 6px;
        }
        .footer-social a:hover { background: var(--clr-primary); border-color: var(--clr-primary); color: #fff; }
        .footer-newsletter-input {
            background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
            border-radius: 9px; color: #fff; padding: 10px 14px;
            font-size: .84rem; width: 100%;
        }
        .footer-newsletter-input::placeholder { color: #475569; }
        .footer-copyright { background: #070c14; padding: .9rem 0; }
        .footer-copyright p { color: #475569; font-size: .78rem; margin: 0; }
        .footer-divider { border-color: rgba(255,255,255,.06); margin: 2rem 0 1.5rem; }

        .footer-pay { display:flex; align-items:center; gap:11px; }
        .footer-pay span {
            display:inline-flex; align-items:center; justify-content:center;
            height:30px; min-width:44px; padding:0 8px;
            background: rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.10);
            border-radius:6px; color:#cbd5e1; font-size:1.05rem;
        }

        /* ── Back to top ── */
        .back-to-top { background: var(--clr-primary) !important; border-color: var(--clr-primary) !important; }

        /* ── Spinner ── */
        #spinner { z-index: 9999; }

        /* ── Alerts ── */
        .alert { border-radius: 10px; font-size: .875rem; border: none; }

        /* ── Section titles ── */
        .sec-title { font-size: 1.6rem; font-weight: 800; color: var(--clr-dark); letter-spacing: -.4px; }
        .sec-sub   { font-size: .85rem; color: var(--clr-muted); }

        /* ── Hero ── */
        .hero-wrap {
            background: linear-gradient(140deg, var(--clr-dark) 0%, #1a2744 60%, #0f172a 100%);
            min-height: 480px; padding: 110px 0 80px;
            position: relative; overflow: hidden;
        }
        .hero-wrap::after {
            content: ''; position: absolute;
            top: -40%; right: -5%; width: 55%; height: 160%;
            background: radial-gradient(ellipse at center, rgba(37,99,235,.18) 0%, transparent 65%);
            pointer-events: none;
        }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(37,99,235,.15); color: #93c5fd;
            border: 1px solid rgba(37,99,235,.25);
            padding: 5px 14px; border-radius: 50px;
            font-size: .76rem; font-weight: 600; margin-bottom: 1.1rem;
        }
        .hero-title {
            font-size: clamp(2rem, 5vw, 3.2rem);
            font-weight: 800; color: #fff;
            line-height: 1.12; letter-spacing: -1.5px;
        }
        .hero-accent { color: var(--clr-primary); }
        .hero-sub { color: #94a3b8; font-size: 1rem; max-width: 460px; margin: 1rem 0 2rem; }
        .hero-stat-val { font-size: 1.4rem; font-weight: 800; color: #fff; }
        .hero-stat-lbl { font-size: .73rem; color: #64748b; }
        .hero-divider { border-left: 1px solid rgba(255,255,255,.1); padding-left: 1.25rem; margin-left: .5rem; }

        /* ── Nav search ── */
        .nav-search {
            display:flex; align-items:center; gap:8px;
            background: var(--clr-light); border:1px solid var(--clr-border);
            border-radius:10px; padding:0 6px 0 13px; max-width:430px; flex:1;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .nav-search:focus-within { border-color: var(--clr-primary); box-shadow:0 0 0 3px rgba(37,99,235,.10); }
        .nav-search i { color:#94a3b8; font-size:.82rem; }
        .nav-search input {
            border:0; background:transparent; outline:none;
            font-size:.85rem; padding:9px 0; flex:1; color:var(--clr-slate); min-width:0;
        }
        .nav-search button {
            border:0; background: var(--clr-primary); color:#fff;
            font-size:.78rem; font-weight:600; padding:7px 15px;
            border-radius:8px; margin:4px 0; white-space:nowrap;
        }
        .nav-search button:hover { filter: brightness(1.08); }
        @media (max-width: 1199px) { .nav-search { max-width:none; margin:12px 0; } }

        /* ── Storefront components (cards, sections, hero, promo) ── */
    /* ── Hero ──────────────────────────────────────────────────────────── */
    .lx-hero {
        background:
            radial-gradient(1100px 520px at 78% 12%, rgba(255,255,255,.14) 0%, transparent 60%),
            linear-gradient(135deg, #123a6b 0%, #1b56a0 45%, #1e6fd0 100%);
        padding: 74px 0 80px;
        position: relative;
        overflow: hidden;
    }
    .lx-hero::after {
        content:''; position:absolute; inset:auto -10% -55% auto;
        width: 620px; height: 620px; border-radius: 50%;
        background: radial-gradient(circle, rgba(255,255,255,.10) 0%, transparent 62%);
    }
    .lx-hero-inner { position: relative; z-index: 2; }
    .lx-rating {
        display:inline-flex; align-items:center; gap:9px;
        font-size:.8rem; color:#dbeafe; margin-bottom:1.35rem;
    }
    .lx-rating .stars { color:#fbbf24; letter-spacing:1px; font-size:.85rem; }
    .lx-h1 {
        font-size: clamp(2.15rem, 4.6vw, 3.5rem);
        font-weight: 800; color:#fff; line-height:1.08;
        letter-spacing:-1.6px; margin-bottom:1.1rem;
    }
    .lx-lead { color: rgba(255,255,255,.82); font-size:1rem; max-width:430px; line-height:1.65; margin-bottom:1.9rem; }
    .lx-cta {
        display:inline-flex; align-items:center; gap:10px;
        background:#0f172a; color:#fff; border:0;
        padding:14px 28px; border-radius:9px;
        font-weight:600; font-size:.92rem; text-decoration:none;
        transition: transform .15s ease, background .15s ease;
    }
    .lx-cta:hover { background:#1e293b; color:#fff; transform: translateY(-2px); }
    .lx-cta-ghost {
        display:inline-flex; align-items:center; gap:8px;
        border:1.5px solid rgba(255,255,255,.35); color:#fff;
        padding:13px 26px; border-radius:9px; font-weight:600;
        font-size:.92rem; text-decoration:none; transition:.15s ease;
    }
    .lx-cta-ghost:hover { background:rgba(255,255,255,.12); color:#fff; }

    /* Floating catalogue tiles that stand in for hero photography */
    .lx-stack { position:relative; height:400px; }
    .lx-tile {
        position:absolute; background:#fff; border-radius:16px;
        box-shadow: 0 22px 48px rgba(8,25,55,.30);
        padding:14px; text-align:center;
    }
    .lx-tile img { width:100%; height:118px; object-fit:contain; }
    .lx-tile .n { font-size:.74rem; font-weight:700; color:#0f172a; margin-top:8px;
                  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .lx-tile .p { font-size:.74rem; font-weight:700; color:#1e6fd0; }
    .lx-tile:nth-child(1) { width:200px; top:10px;  left:2%;   transform:rotate(-5deg); }
    .lx-tile:nth-child(2) { width:224px; top:96px;  left:33%;  z-index:3; }
    .lx-tile:nth-child(3) { width:190px; top:34px;  right:1%;  transform:rotate(5deg); }
    .lx-tile:nth-child(4) { width:186px; bottom:6px; left:12%; transform:rotate(3deg); }

    /* ── Brand strip ───────────────────────────────────────────────────── */
    .lx-brands { border-bottom:1px solid var(--clr-border); background:#fff; padding:26px 0; }
    .lx-brand {
        font-weight:800; font-size:1.02rem; letter-spacing:.6px;
        color:#94a3b8; opacity:.85; white-space:nowrap;
        transition:.2s ease;
    }
    .lx-brand:hover { color:#334155; opacity:1; }

    /* ── Section heads ─────────────────────────────────────────────────── */
    .lx-head { font-size:1.55rem; font-weight:800; color:#0f172a; letter-spacing:-.6px; }
    .lx-head span { color: var(--clr-primary); }
    .lx-sub  { font-size:.87rem; color:#64748b; }
    .lx-link { font-size:.85rem; font-weight:600; color:#0f172a; text-decoration:none; }
    .lx-link:hover { color: var(--clr-primary); }

    /* ── Category tiles ────────────────────────────────────────────────── */
    .lx-cat {
        display:block; position:relative; border-radius:14px; overflow:hidden;
        height:190px; background:#eef2f6; text-decoration:none;
        box-shadow: var(--shadow-sm); transition: transform .2s ease, box-shadow .2s ease;
    }
    .lx-cat:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
    .lx-cat img { width:100%; height:100%; object-fit:contain; padding:22px 22px 46px; }
    .lx-cat-label {
        position:absolute; left:0; right:0; bottom:0;
        background: linear-gradient(to top, rgba(15,23,42,.88) 30%, transparent);
        color:#fff; padding:26px 14px 12px;
        font-size:.86rem; font-weight:700; text-align:center;
    }
    .lx-cat-count { font-size:.72rem; font-weight:500; opacity:.75; display:block; }

    /* ── Product cards ─────────────────────────────────────────────────── */
    .lx-p {
        background:#fff; border:1px solid var(--clr-border); border-radius:14px;
        overflow:hidden; height:100%; display:flex; flex-direction:column;
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }
    .lx-p:hover { transform:translateY(-5px); box-shadow:var(--shadow-md); border-color:#cbd5e1; }
    .lx-p-img { position:relative; height:200px; background:#fff; padding:14px; }
    .lx-p-img img { width:100%; height:100%; object-fit:contain; }
    .lx-badge {
        position:absolute; top:11px; left:11px; z-index:2;
        font-size:.62rem; font-weight:800; letter-spacing:.7px; text-transform:uppercase;
        padding:4px 9px; border-radius:5px; color:#fff;
    }
    .b-new   { background:#0f766e; }
    .b-best  { background:#0f172a; }
    .b-trend { background:#7c3aed; }
    .b-sale  { background:#dc2626; }
    .lx-wish {
        position:absolute; top:9px; right:9px; z-index:2;
        width:32px; height:32px; border-radius:50%;
        background:#fff; border:1px solid var(--clr-border);
        display:flex; align-items:center; justify-content:center;
        color:#94a3b8; font-size:.8rem;
    }
    .lx-p-body { padding:14px 15px 16px; display:flex; flex-direction:column; flex:1; }
    .lx-p-cat  { font-size:.68rem; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; }
    .lx-p-name { font-size:.88rem; font-weight:600; color:#0f172a; line-height:1.4; margin:5px 0 9px; }
    .lx-p-name a { color:inherit; text-decoration:none; }
    .lx-p-name a:hover { color:var(--clr-primary); }
    .lx-stars { font-size:.7rem; color:#f59e0b; letter-spacing:.5px; }
    .lx-stars span { color:#94a3b8; font-size:.7rem; margin-left:4px; letter-spacing:0; }
    .lx-price  { font-size:1rem; font-weight:800; color:#0f172a; }
    .lx-price-was { font-size:.8rem; color:#94a3b8; text-decoration:line-through; margin-left:6px; }
    .lx-oos {
        position:absolute; inset:0; background:rgba(255,255,255,.78);
        display:flex; align-items:center; justify-content:center; z-index:3;
        font-size:.76rem; font-weight:700; color:#dc2626; letter-spacing:.4px;
    }
    .lx-add {
        width:100%; border:0; border-radius:8px; padding:10px;
        background:#0f172a; color:#fff; font-size:.82rem; font-weight:600;
        display:flex; align-items:center; justify-content:center; gap:7px;
        text-decoration:none; transition:.15s ease;
    }
    .lx-add:hover { background:var(--clr-primary); color:#fff; }
    .lx-add[disabled] { background:#e2e8f0; color:#94a3b8; cursor:not-allowed; }

    /* ── Promo banner ──────────────────────────────────────────────────── */
    .lx-promo {
        border-radius:18px; overflow:hidden; position:relative;
        background:
            radial-gradient(680px 340px at 82% 50%, rgba(30,111,208,.42) 0%, transparent 62%),
            linear-gradient(115deg, #0b1220 0%, #16233c 55%, #0d1b30 100%);
        padding:56px 52px;
    }
    .lx-promo-eyebrow { font-size:.72rem; color:#7dd3fc; letter-spacing:1.4px; text-transform:uppercase; font-weight:700; }
    .lx-promo h3 { font-size:clamp(1.5rem,3vw,2.15rem); font-weight:800; color:#fff; line-height:1.2; letter-spacing:-.8px; margin:12px 0 20px; max-width:420px; }
    .lx-promo-ring {
        position:absolute; right:-70px; top:50%; transform:translateY(-50%);
        width:330px; height:330px; border-radius:50%;
        border:1px solid rgba(255,255,255,.14);
    }
    .lx-promo-ring::after {
        content:''; position:absolute; inset:42px; border-radius:50%;
        border:1px solid rgba(255,255,255,.10);
    }

    /* ── Tabs ──────────────────────────────────────────────────────────── */
    .lx-tabs { display:flex; gap:8px; flex-wrap:wrap; justify-content:center; margin-bottom:26px; }
    .lx-tab {
        border:1px solid var(--clr-border); background:#fff; color:#475569;
        padding:7px 17px; border-radius:50px; font-size:.8rem; font-weight:600;
        cursor:pointer; transition:.15s ease;
    }
    .lx-tab:hover { border-color:#cbd5e1; }
    .lx-tab.active { background:#0f172a; color:#fff; border-color:#0f172a; }

    /* ── Deal of the week ── */
    .lx-deal {
        border-radius:18px; padding:38px;
        background: linear-gradient(135deg, #f8fafc 0%, #eef4fb 100%);
        border:1px solid var(--clr-border);
    }
    .lx-deal-eyebrow { font-size:.7rem; font-weight:800; letter-spacing:1.3px; text-transform:uppercase; color:#dc2626; }
    .lx-deal-title { font-size:1.75rem; font-weight:800; color:#0f172a; letter-spacing:-.7px; margin:8px 0 10px; }
    .lx-deal-text { font-size:.87rem; color:#64748b; max-width:330px; margin-bottom:20px; }
    .lx-clock { display:flex; gap:10px; }
    .lx-clock div {
        background:#0f172a; color:#fff; border-radius:10px;
        padding:11px 0; min-width:62px; text-align:center;
    }
    .lx-clock b { display:block; font-size:1.3rem; font-weight:800; line-height:1; font-variant-numeric:tabular-nums; }
    .lx-clock span { font-size:.62rem; text-transform:uppercase; letter-spacing:.8px; opacity:.62; }

    /* ── Testimonials ── */
    .lx-quote {
        background:#fff; border:1px solid var(--clr-border); border-radius:14px;
        padding:24px; height:100%;
    }
    .lx-quote p { font-size:.87rem; color:#475569; line-height:1.65; margin-bottom:18px; }
    .lx-quote-by { display:flex; align-items:center; gap:11px; }
    .lx-quote-by b { display:block; font-size:.83rem; color:#0f172a; }
    .lx-quote-by small { font-size:.74rem; color:#94a3b8; }
    .lx-avatar {
        width:38px; height:38px; border-radius:50%;
        background: var(--clr-primary); color:#fff;
        display:flex; align-items:center; justify-content:center;
        font-weight:800; font-size:.9rem; flex-shrink:0;
    }

    @media (max-width: 991px) { .lx-deal { padding:26px; } }

    /* ── Trust row ─────────────────────────────────────────────────────── */
    .lx-trust {
        display:flex; align-items:center; gap:13px;
        background:#f8fafc; border:1px solid var(--clr-border);
        border-radius:12px; padding:17px 20px; height:100%;
    }
    .lx-trust i { font-size:1.1rem; color:var(--clr-primary); }
    .lx-trust b { display:block; font-size:.85rem; color:#0f172a; font-weight:700; }
    .lx-trust span { font-size:.76rem; color:#64748b; }

    @media (max-width: 991px) {
        .lx-stack { display:none; }
        .lx-hero { padding:56px 0 60px; }
        .lx-promo { padding:38px 26px; }
        .lx-promo-ring { display:none; }
    }

        /* Cart badge on body */
        .qty-display {
            background: var(--clr-light); border: 1px solid var(--clr-border);
            border-radius: 8px; padding: 6px 14px;
            font-size: .875rem; font-weight: 500; color: var(--clr-slate);
            min-width: 44px; text-align: center;
        }
    </style>

    @yield('frontend_css')
</head>

<body>
    <!-- Spinner -->
    <div id="spinner" class="show w-100 vh-100 bg-white position-fixed translate-middle top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-grow" style="color: var(--clr-primary);" role="status"></div>
    </div>

    <!-- Topbar -->
    <div class="store-topbar d-none d-lg-block">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-map-marker-alt me-1 text-blue-400"></i>
                <a href="#" class="me-3">{{ $setting->address ?? '' }}</a>
                <i class="fas fa-envelope me-1"></i>
                <a href="#">{{ $setting->default_email ?? '' }}</a>
            </div>
            <div class="topbar-social">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
            </div>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="store-navbar navbar navbar-expand-xl">
        <div class="container d-flex align-items-center">
            <a href="{{ route('store.landing') }}" class="navbar-brand-name me-4">
                {{ $setting->business_name ?? 'Store' }}
            </a>
            <button class="navbar-toggler border-0 ms-auto me-3" type="button"
                    data-bs-toggle="collapse" data-bs-target="#navMain">
                <i class="fas fa-bars" style="color: var(--clr-slate);"></i>
            </button>
            <div class="collapse navbar-collapse" id="navMain">
                <ul class="navbar-nav gap-1 me-3">
                    <li class="nav-item">
                        <a href="{{ route('store.landing') }}"
                           class="nav-pill-link {{ Route::is('store.landing') ? 'active' : '' }}">
                            Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('store.shop') }}"
                           class="nav-pill-link {{ Route::is('store.shop') ? 'active' : '' }}">
                            Shop
                        </a>
                    </li>
                    @php
                        $navCategories = \App\Models\Category::whereNotNull('parent_id')
                            ->withCount('products')->having('products_count', '>', 0)->take(8)->get();
                    @endphp
                    @if($navCategories->count())
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-pill-link dropdown-toggle" data-bs-toggle="dropdown">Categories</a>
                        <ul class="dropdown-menu" style="min-width:230px;">
                            @foreach($navCategories as $navCat)
                                <li>
                                    <a class="dropdown-item d-flex justify-content-between align-items-center"
                                       href="{{ route('store.shop', ['category' => $navCat->id]) }}"
                                       style="padding:.5rem .85rem; border-radius:8px; font-size:.85rem;">
                                        {{ $navCat->name }}
                                        <span style="font-size:.7rem; color:var(--clr-muted);">{{ $navCat->products_count }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                    @endif
                </ul>

                {{-- Search — the shop route already understands ?q= --}}
                <form action="{{ route('store.shop') }}" method="GET" class="nav-search me-auto">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="search" name="q" value="{{ request('q') }}"
                           placeholder="Search for phones, laptops, audio…" autocomplete="off">
                    <button type="submit">Search</button>
                </form>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    @php
                        $cartItems = session('cart');
                        $itemCount = is_array($cartItems) ? count($cartItems) : 0;
                    @endphp
                    <a href="{{ route('cart.view') }}" class="cart-btn" title="Cart">
                        <i class="fa fa-shopping-bag"></i>
                        <span class="cart-badge">{{ $itemCount }}</span>
                    </a>

                    @if(Auth::guard('customer')->check())
                        @php $authCustomer = Auth::guard('customer')->user(); @endphp
                        <div class="dropdown">
                            <button class="btn p-0 border-0 bg-transparent d-flex align-items-center gap-2"
                                    id="custDrop" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="user-avatar">
                                    {{ strtoupper(substr($authCustomer->name ?? 'U', 0, 1)) }}
                                </div>
                                <div class="d-none d-md-block text-start" style="line-height:1.2;">
                                    <div style="font-size:.8rem; font-weight:700; color:var(--clr-dark);">{{ $authCustomer->name }}</div>
                                </div>
                                <i class="fas fa-chevron-down" style="font-size:.6rem; color:var(--clr-muted);"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="custDrop" style="min-width:230px;">
                                {{-- User info header --}}
                                <li style="padding:.9rem 1rem .75rem; border-bottom:1px solid var(--clr-border);">
                                    <div style="display:flex; align-items:center; gap:.65rem;">
                                        <div style="width:38px; height:38px; border-radius:10px; background:var(--clr-primary); color:#fff; font-size:.9rem; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                            {{ strtoupper(substr($authCustomer->name ?? 'U', 0, 1)) }}
                                        </div>
                                        <div style="min-width:0;">
                                            <div style="font-size:.85rem; font-weight:700; color:var(--clr-dark); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                                {{ $authCustomer->name }} {{ $authCustomer->last_name }}
                                            </div>
                                            <div style="font-size:.72rem; color:var(--clr-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                                {{ $authCustomer->email }}
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li style="padding:.35rem .5rem 0;">
                                    <a class="dropdown-item" href="{{ route('customer.profile.edit') }}"
                                       style="display:flex; align-items:center; gap:.6rem; padding:.55rem .75rem; border-radius:8px; font-size:.855rem;">
                                        <span style="width:28px; height:28px; border-radius:8px; background:rgba(37,99,235,.1); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                            <i class="fas fa-user-circle" style="color:var(--clr-primary); font-size:.75rem;"></i>
                                        </span>
                                        My Profile
                                    </a>
                                </li>
                                <li style="padding:0 .5rem;">
                                    <a class="dropdown-item" href="{{ route('store.orders') }}"
                                       style="display:flex; align-items:center; gap:.6rem; padding:.55rem .75rem; border-radius:8px; font-size:.855rem;">
                                        <span style="width:28px; height:28px; border-radius:8px; background:rgba(245,158,11,.1); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                            <i class="fas fa-box" style="color:#f59e0b; font-size:.75rem;"></i>
                                        </span>
                                        My Orders
                                    </a>
                                </li>
                                <li style="padding:0 .5rem .35rem;"><hr class="dropdown-divider my-1"></li>
                                <li style="padding:0 .5rem .5rem;">
                                    <form method="POST" action="{{ route('customer.logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger"
                                                style="display:flex; align-items:center; gap:.6rem; padding:.55rem .75rem; border-radius:8px; font-size:.855rem;">
                                            <span style="width:28px; height:28px; border-radius:8px; background:#fff1f2; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                                <i class="fas fa-sign-out-alt" style="color:#ef4444; font-size:.75rem;"></i>
                                            </span>
                                            Sign Out
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @else
                        <a href="{{ route('customer.login') }}" class="btn-prim" style="text-decoration:none;">
                            <i class="fas fa-sign-in-alt"></i> Sign In
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <!-- Modal Search -->
    <div class="modal fade" id="searchModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Search</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body d-flex align-items-center">
                    <div class="input-group w-75 mx-auto">
                        <input type="search" class="form-control p-3 field-input" placeholder="Search products...">
                        <span class="input-group-text p-3"><i class="fa fa-search"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @yield('frontend_content')

    <!-- Footer -->
    <footer class="store-footer pt-5 mt-5">
        <div class="container">
            <div class="row g-4 py-4">
                <div class="col-lg-4 col-md-6">
                    <div class="footer-brand mb-2">{{ $setting->business_name ?? 'Store' }}</div>
                    <p class="footer-about mb-3">
                        Your trusted destination for quality products at the best prices. Shop with confidence, delivered fast.
                    </p>
                    <div class="footer-social">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>

                <div class="col-lg-2 col-md-3 col-6">
                    <div class="footer-h">Shop</div>
                    <a href="{{ route('store.landing') }}" class="footer-a">Home</a>
                    <a href="{{ route('store.shop') }}" class="footer-a">All Products</a>
                    <a href="{{ route('cart.view') }}" class="footer-a">My Cart</a>
                    @if(Auth::guard('customer')->check())
                        <a href="{{ route('store.orders') }}" class="footer-a">Orders</a>
                    @endif
                </div>

                <div class="col-lg-2 col-md-3 col-6">
                    <div class="footer-h">Account</div>
                    @if(Auth::guard('customer')->check())
                        <a href="{{ route('customer.profile.edit') }}" class="footer-a">Profile</a>
                        <a href="{{ route('store.orders') }}" class="footer-a">Order History</a>
                    @else
                        <a href="{{ route('customer.login') }}" class="footer-a">Sign In</a>
                        <a href="{{ route('customer.register') }}" class="footer-a">Register</a>
                    @endif
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="footer-h">Newsletter</div>
                    <p class="footer-about mb-3">Get the latest deals and updates straight to your inbox.</p>
                    <div class="d-flex gap-2">
                        <input type="email" class="footer-newsletter-input" placeholder="Enter your email">
                        <button class="btn-prim flex-shrink-0" style="border-radius:9px; padding:10px 16px;">Subscribe</button>
                    </div>
                    @if($setting->address ?? false)
                    <p class="footer-about mt-3 mb-0">
                        <i class="fas fa-map-marker-alt me-1"></i> {{ $setting->address }}<br>
                        <i class="fas fa-envelope me-1 mt-1"></i> {{ $setting->default_email ?? '' }}
                    </p>
                    @endif
                </div>
            </div>

            <hr class="footer-divider">

            <div class="footer-copyright d-flex justify-content-between align-items-center flex-wrap gap-3 pb-4">
                <p class="mb-0">&copy; {{ date('Y') }} {{ $setting->business_name ?? 'Store' }}. All rights reserved.</p>

                <div class="footer-pay">
                    <span title="Visa"><i class="fab fa-cc-visa"></i></span>
                    <span title="Mastercard"><i class="fab fa-cc-mastercard"></i></span>
                    <span title="mada"><i class="fas fa-credit-card"></i></span>
                    <span title="Apple Pay"><i class="fab fa-apple-pay"></i></span>
                    <span title="Cash on delivery"><i class="fas fa-money-bill-wave"></i></span>
                </div>

                <p class="mb-0">Developed by <a href="#" style="color:#64748b; text-decoration:none; font-weight:500;">{{ $setting->developed_by ?? '' }}</a></p>
            </div>
        </div>
    </footer>

    <!-- Back to Top -->
    <a href="#" class="btn btn-primary border-3 border-primary rounded-circle back-to-top">
        <i class="fa fa-arrow-up"></i>
    </a>

    <!-- JS -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('build/assets/frontend/lib/easing/easing.min.js') }}"></script>
    <script src="{{ asset('build/assets/frontend/lib/waypoints/waypoints.min.js') }}"></script>
    <script src="{{ asset('build/assets/frontend/lib/lightbox/js/lightbox.min.js') }}"></script>
    <script src="{{ asset('build/assets/frontend/lib/owlcarousel/owl.carousel.min.js') }}"></script>
    <script src="{{ asset('build/assets/frontend/js/main.js') }}"></script>

    @yield('frontend_js')
</body>
</html>
