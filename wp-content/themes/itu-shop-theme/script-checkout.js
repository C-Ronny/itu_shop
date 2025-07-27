document.addEventListener('DOMContentLoaded', function() {
  // Exit early if not on the checkout page
  if (!document.querySelector('.cart-page') || !document.querySelector('#checkout-form')) {
      console.log('ITU Shop: script-checkout.js skipped on non-checkout page');
      return;
  }

  const checkoutForm = document.getElementById('checkout-form');
  const prototypeModal = document.getElementById('prototype-modal');
  const modalRedirectButton = document.getElementById('modal-redirect');
  const modalCloseButton = document.getElementById('modal-close');

  // Validate required elements
  if (!checkoutForm || !prototypeModal || !modalRedirectButton || !modalCloseButton) {
      console.error('ITU Shop: Checkout elements not found:', {
          checkoutForm: !!checkoutForm,
          prototypeModal: !!prototypeModal,
          modalRedirectButton: !!modalRedirectButton,
          modalCloseButton: !!modalCloseButton
      });
      return;
  }

  // Handle form submission
  checkoutForm.addEventListener('submit', function(event) {
      event.preventDefault();
      console.log('ITU Shop: Checkout form submitted');

      // Validate form fields
      const fullName = document.getElementById('full-name').value.trim();
      const email = document.getElementById('email').value.trim();
      const address = document.getElementById('address').value.trim();

      if (!fullName || !email || !address) {
          alert('Please fill in all required fields.');
          console.error('ITU Shop: Form validation failed:', { fullName, email, address });
          return;
      }

      // Show the modal
      prototypeModal.style.display = 'block';
      prototypeModal.classList.add('show');
      console.log('ITU Shop: Prototype modal shown');

      // Auto-hide modal after 5 seconds (longer than cart-notification for readability)
      setTimeout(() => {
          prototypeModal.classList.remove('show');
          prototypeModal.style.display = 'none';
          console.log('ITU Shop: Prototype modal auto-hidden');
      }, 5000);

      // Focus on the redirect button for accessibility
      modalRedirectButton.focus();
  });

  // Handle redirect button click
  modalRedirectButton.addEventListener('click', function() {
      console.log('ITU Shop: Redirecting to official ITU Shop');
      window.location.href = 'https://shop.itu.int';
  });

  // Handle close button click
  modalCloseButton.addEventListener('click', function() {
      prototypeModal.classList.remove('show');
      prototypeModal.style.display = 'none';
      console.log('ITU Shop: Prototype modal closed');
      // Return focus to the submit button for accessibility
      checkoutForm.querySelector('button[type="submit"]').focus();
  });

  // Close modal on Escape key for accessibility
  document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape' && prototypeModal.style.display === 'block') {
          prototypeModal.classList.remove('show');
          prototypeModal.style.display = 'none';
          console.log('ITU Shop: Prototype modal closed via Escape key');
          checkoutForm.querySelector('button[type="submit"]').focus();
      }
  });
});