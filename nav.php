<?php
require_once __DIR__ . '/dbconfig.php';
$current = basename($_SERVER['SCRIPT_NAME']);
$links = [
  'orders.php'    => ['Orders', 'fa-receipt'],
  'customers.php' => ['Customers', 'fa-users'],
  'products.php'  => ['Products', 'fa-fire-flame-simple'],
  'report.php'    => ['Reports & Analytics', 'fa-chart-line'],
];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="my">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Admin') ?> | ကောင်းကံလင်း Admin Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Myanmar:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            vanilla: '#FAF7F5',
            'rose-soft': '#FBE8EC',
            copper: '#C86D7C',
            'copper-dark': '#B35B6A',
            gold: '#E8B4B8',
            charcoal: '#231F20',
            sand: '#6F6164'
          },
          fontFamily: {
            sans: ['"Plus Jakarta Sans"', '"Noto Sans Myanmar"', 'sans-serif']
          }
        }
      }
    }
  </script>
  <style>
    body {
      background: #FAF7F5;
      color: #231F20;
    }

    .card {
      background: #fff;
      border: 1px solid #FBE8EC;
      border-radius: 1rem;
      box-shadow: 0 1px 2px rgba(200, 109, 124, .08);
      transition: box-shadow .2s;
    }

    .card:hover {
      box-shadow: 0 6px 18px -6px rgba(200, 109, 124, .25);
    }

    .btn {
      background: #C86D7C;
      color: #fff;
      border-radius: .75rem;
      padding: .5rem 1rem;
      font-weight: 600;
      font-size: .875rem;
      transition: background .15s;
      display: inline-flex;
      align-items: center;
      gap: .4rem;
    }

    .btn:hover {
      background: #B35B6A;
    }

    .btn-ghost {
      background: #fff;
      color: #C86D7C;
      border: 1px solid #E8B4B8;
    }

    .btn-ghost:hover {
      background: #FBE8EC;
    }

    .input {
      width: 100%;
      border: 1px solid #F3D5DA;
      border-radius: .6rem;
      padding: .5rem .75rem;
      font-size: .875rem;
      background: #fff;
    }

    select.input {
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      min-height: 2.75rem;
      line-height: 1.5;
      cursor: pointer;

      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%206B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right .75rem center;
      padding-right: 2rem;
    }

    .input:focus {
      outline: 2px solid #E8B4B8;
      border-color: #C86D7C;
    }

    .label {
      display: block;
      font-size: .75rem;
      font-weight: 600;
      color: #6F6164;
      margin-bottom: .25rem;
    }

    th {
      text-align: left;
      font-size: .75rem;
      font-weight: 700;
      color: #C86D7C;
      padding: .75rem 1rem;
      background: #FBE8EC;
      white-space: nowrap;
    }

    td {
      padding: .75rem 1rem;
      font-size: .875rem;
      border-top: 1px solid #FBE8EC;
      vertical-align: middle;
    }

    tbody tr:hover {
      background: #FFFAFB;
    }

    .modal-bg {
      position: fixed;
      inset: 0;
      background: rgba(35, 31, 32, .45);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      z-index: 60;
    }

    .modal-bg.open {
      display: flex;
    }

    .modal-box {
      background: #fff;
      border-radius: 1.25rem;
      width: 100%;
      max-height: 92vh;
      overflow-y: auto;
      border: 1px solid #FBE8EC;
    }
  </style>
</head>

<body class="font-sans min-h-screen">
  <header class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-rose-soft">
    <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
      <a href="orders.php" class="flex items-center gap-2">
        <div
          class="w-10 h-10 rounded-full bg-gradient-to-tr from-copper to-gold flex items-center justify-center shadow-md group-hover:scale-105 transition-transform duration-300">
          <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2">
            <path d="M12 2c0 0-4 4-4 8 0 2.2 1.8 4 4 4s4-1.8 4-4c0-4-4-8-4-8z" fill="#FFF7AD"
              stroke="#E8B4B8" />
            <path d="M12 14v8" stroke="currentColor" stroke-linecap="round" />
            <path d="M8 22h8" stroke="currentColor" stroke-linecap="round" />
          </svg>
        </div>
        <span class="font-bold text-copper text-lg">ကောင်းကံလင်း <span class="text-charcoal font-semibold">Admin Portal</span></span>
      </a>
      <nav class="flex flex-wrap gap-1.5">
        <?php foreach ($links as $href => [$label, $icon]): $on = $current === $href; ?>
          <a href="<?= $href ?>" class="px-3.5 py-1.5 rounded-full text-sm font-semibold flex items-center gap-1.5 transition <?= $on ? 'bg-copper text-white' : 'text-sand hover:bg-rose-soft hover:text-copper' ?>">
            <i class="fa-solid <?= $icon ?> text-xs"></i><?= e($label) ?>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>
  </header>
  <?php if ($flash): ?>
    <div id="flash" class="max-w-7xl mx-auto px-4 mt-4">
      <div class="rounded-xl border px-4 py-3 text-sm font-medium flex items-center justify-between <?= $flash['type'] === 'error' ? 'bg-rose-100 text-rose-800 border-rose-200' : 'bg-emerald-100 text-emerald-800 border-emerald-200' ?>">
        <span><i class="fa-solid <?= $flash['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check' ?> mr-2"></i><?= e($flash['msg']) ?></span>
        <button onclick="document.getElementById('flash').remove()" class="opacity-60 hover:opacity-100"><i class="fa-solid fa-xmark"></i></button>
      </div>
    </div>
  <?php endif; ?>
  <script>
    function openModal(id) {
      document.getElementById(id).classList.add('open');
    }

    function closeModal(id) {
      document.getElementById(id).classList.remove('open');
    }
    document.addEventListener('click', e => {
      if (e.target.classList.contains('modal-bg')) e.target.classList.remove('open');
    });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') document.querySelectorAll('.modal-bg.open').forEach(m => m.classList.remove('open'));
    });
    const fmt = n => Math.round(n).toLocaleString('en-US') + ' MMK';
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    } [c]));
  </script>