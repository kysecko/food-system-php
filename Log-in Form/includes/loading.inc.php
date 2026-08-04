<div id="loading-overlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(255,255,255,0.7); z-index:99999; align-items:center; justify-content:center;">
    <svg style="position: absolute; width: 0; height: 0;">
      <filter id="goo">
      <feGaussianBlur in="SourceGraphic" stdDeviation="12"></feGaussianBlur>
      <feColorMatrix values="0 0 0 0 0 
                0 0 0 0 0 
                0 0 0 0 0 
                0 0 0 48 -7"></feColorMatrix>
    </filter>
    </svg>
    <div class="loader"></div>
</div>
<link rel="stylesheet" href="/Log-in%20Form/design/loading.css">