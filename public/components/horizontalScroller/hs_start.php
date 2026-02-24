<?php
// Horizontal Scroller Start
?>

<script>
  function hs_scroll(x, event) {
    const wrapper = event.currentTarget.parentNode.querySelector('.hs_Wrapper');
    const scrollAmount = wrapper.querySelector(':first-child')?.offsetWidth + 24 || 350;
    if (x === 0) {
      wrapper.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    } else if (x === 1) {
      wrapper.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }
  }
</script>

<div class="hs">
  <button aria-label="Scroll left" class="hs_Btn" onclick="hs_scroll(0, event)">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left-icon lucide-chevron-left"><path d="m15 18-6-6 6-6"/></svg>
  </button>
  <button aria-label="Scroll right" class="hs_Btn" onclick="hs_scroll(1, event)">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right"><path d="m9 18 6-6-6-6"/></svg>
  </button>
  <div class="hs_Wrapper">
