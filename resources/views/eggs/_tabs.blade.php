<x-underline-tabs :tabs="[
    'logging'     => ['label' => 'Egg Logging', 'icon' => 'egg',         'route' => 'eggs.logging'],
    'recent-logs' => ['label' => 'Recent Logs', 'icon' => 'clipboard',   'route' => 'eggs.recent-logs'],
    'stocks'      => ['label' => 'Egg Stocks',  'icon' => 'package',     'route' => 'eggs.stocks'],
    'preorders'   => ['label' => 'Pre-Orders',  'icon' => 'shopping-bag', 'route' => 'eggs.preorders'],
]" active="{{ $activeTab }}" />
