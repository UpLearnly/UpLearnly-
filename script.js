// Wait for DOM
document.addEventListener('DOMContentLoaded', () => {
  // Add to Cart
  window.addToCart = function(id) {
    fetch('index.php?view=cart', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({action:'add', id:id})
    })
    .then(res => res.json())
    .then(data => {
      document.getElementById('cart-count').innerText = data.count;
      alert('Item added!');
    });
  };

  // Remove from Cart
  window.removeFromCart = function(id) {
    fetch('index.php?view=cart', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({action:'remove', id:id})
    })
    .then(res => res.json())
    .then(data => {
      document.getElementById('cart-count').innerText = data.count;
      location.reload();
    });
  };

  // IntersectionObserver for fade-in
  const faders = document.querySelectorAll('.fade-in-section');
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });
  faders.forEach(f => observer.observe(f));

  // Hero slider
  let slideIndex = 0;
  function showSlides() {
    const slides = document.querySelectorAll('#hero-slider .slide');
    slides.forEach(s => s.classList.remove('active'));
    if (slides.length > 0) {
      slides[slideIndex].classList.add('active');
      slideIndex = (slideIndex + 1) % slides.length;
    }
    setTimeout(showSlides, 5000);
  }
  showSlides();
});