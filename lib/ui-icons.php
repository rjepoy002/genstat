<?php
declare(strict_types=1);

function ui_icon(string $name): string {
 $paths=[
  'bolt'=>'M13 2 4 14h7l-1 8 10-12h-7l1-8Z',
  'open'=>'M3 7h6l2 3h10l-3 10H3V7Zm0 0V4h6l2 3h8v3',
  'save'=>'M5 3h12l4 4v14H3V3h2Zm2 0v6h10V3M7 21v-8h10v8',
  'export'=>'M12 3v12m-5-5 5 5 5-5M4 15v6h16v-6',
  'lock'=>'M6 11h12v10H6V11Zm3 0V7a3 3 0 0 1 6 0v4m-3 4v2',
  'history'=>'M3 12a9 9 0 1 1 2.6 6.4M3 5v7h7m2-5v5l4 2',
  'zoom'=>'M21 21l-6-6m2-6a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z',
  'sync'=>'M20 7v5h-5M4 17v-5h5M5 8a8 8 0 0 1 13-3l2 3M4 16l2 3a8 8 0 0 0 13-3',
  'shield-check'=>'M12 22s7-3.5 7-10V5l-7-3-7 3v7c0 6.5 7 10 7 10Zm-3-10 2 2 4-4',
  'cloud-download'=>'M12 13v8m-3-3 3 3 3-3m3-2a4 4 0 0 0-.5-8h-1A6 6 0 1 0 6 16',
  'circle-alert'=>'M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0ZM12 8v4m0 4h.01',
  'circle-check'=>'M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Zm-14 0 3 3 5-6',
  'spinner'=>'M21 12a9 9 0 1 1-9-9',
  'warning'=>'m12 3 10 18H2L12 3Zm0 6v5m0 3h.01',
 ];
 return '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.$paths[$name].'"/></svg>';
}
