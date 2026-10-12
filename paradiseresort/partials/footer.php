  </main>
  <footer class="site-footer mt-auto text-light pt-5">
    <div class="container pb-4">
      <div class="row g-4">
        <div class="col-12 col-md-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <img src="/paradiseresort/logo/logo.jpeg" onerror="this.onerror=null;this.src='/paradiseresort/logo/logo.jpg'" alt="Logo" width="36" height="36" class="rounded-circle object-fit-cover">
            <h5 class="mb-0">Peterlyn's Resort</h5>
          </div>
          <p class="text-secondary">Sun, sea, and serenity. Modern rooms with complimentary resort access for every booking.</p>
        </div>
        <div class="col-6 col-md-2">
          <h5>Quick Links</h5>
          <ul class="list-unstyled small mt-3">
            <li class="mb-2"><a href="/paradiseresort/index.php">Home</a></li>
            <li class="mb-2"><a href="/paradiseresort/index.php#rooms">Rooms</a></li>
            <li class="mb-2"><a href="/paradiseresort/contact.php">Contact</a></li>
            <?php if (empty($_SESSION['user'])): ?>
              <li class="mb-2"><a href="/paradiseresort/auth/login.php">Login</a></li>
              <li class="mb-2"><a href="/paradiseresort/auth/register.php">Sign Up</a></li>
            <?php else: ?>
              <li class="mb-2"><a href="/paradiseresort/my_bookings.php">My Bookings</a></li>
            <?php endif; ?>
          </ul>
        </div>
        <div class="col-6 col-md-3">
          <h5>Contact</h5>
          <div class="small mt-3 text-secondary">
            Seaside Blvd, PH<br>
            +63 900 000 0000<br>
            peterlynresort.local
          </div>
        </div>
        <div class="col-12 col-md-3">
          <h5>Get social</h5>
          <div class="mt-3">
            <a class="icon-circle" href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
            <a class="icon-circle" href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
            <a class="icon-circle" href="#" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
            <a class="icon-circle" href="#" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
            <a class="icon-circle" href="#" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
          </div>
          <div class="small text-secondary mt-3">Payment methods:</div>
          <div class="mt-2 d-flex gap-2 text-secondary">
            <i class="bi bi-credit-card-2-front"></i>
            <i class="bi bi-paypal"></i>
            <i class="bi bi-wallet2"></i>
          </div>
        </div>
      </div>
      <div class="d-flex flex-column flex-md-row align-items-center justify-content-between pt-4 pb-3 border-top border-light-subtle mt-4 small text-secondary">
        <div>© <?= date('Y') ?> Peterlyn's Resort. All rights reserved.</div>
        <div>Free resort access included with every hotel booking.</div>
      </div>
    </div>
  </footer>
  <!-- Image Lightbox Modal -->
  <div class="modal fade" id="imageLightbox" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content bg-dark border-0">
        <div class="modal-body p-0">
          <img id="lightboxImg" src="" alt="preview" class="w-100 object-fit-contain" style="max-height: 85vh;">
        </div>
      </div>
    </div>
  </div>
  <script src="/paradiseresort/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script>
    // Open any room image in a modal when clicked
    document.addEventListener('click', function(e){
      const img = e.target.closest('img.js-viewable');
      if (!img) return;
      const full = img.getAttribute('data-full') || img.currentSrc || img.src;
      const el = document.getElementById('lightboxImg');
      el.src = full; el.alt = img.alt || 'preview';
      const modal = new bootstrap.Modal(document.getElementById('imageLightbox'));
      modal.show();
    });
  </script>
</body>
</html>


