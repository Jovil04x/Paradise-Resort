import {
  ACTIVE_BOOKING_STATUSES,
  DEFAULT_ROOMS,
  cancelBooking,
  countNights,
  createBooking,
  enumerateNights,
  firebaseModeLabel,
  getAllBookings,
  observeChatConversations,
  observeChatMessages,
  getMyBookings,
  getRoomAvailability,
  getRooms,
  observeUser,
  registerUser,
  sendChatMessage,
  sendContactMessage,
  signIn,
  signOut,
  updateBookingStatus
} from './data-service.js?v=20261012g';

const app = document.getElementById('app');
const toast = document.getElementById('toast');
const assetBase = location.pathname.startsWith('/paradiseresort') ? '/paradiseresort' : '';

function assetPath(value) {
  return `${assetBase}/${String(value || '').replace(/^\/+/, '')}`;
}

function localIsoDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

const today = localIsoDate(new Date());

const state = {
  user: null,
  rooms: DEFAULT_ROOMS,
  roomsLoading: true,
  route: 'home',
  activeRoom: null,
  availability: null,
  availabilityRoom: null,
  availabilityLoading: false,
  availabilityAttemptedFor: null,
  availabilityError: null,
  calendarMonth: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
  booking: { checkIn: '', checkOut: '', guests: 1 },
  bookings: [],
  bookingsLoading: false,
  bookingsLoadedFor: null,
  adminBookings: [],
  adminLoading: false,
  adminLoadedFor: null,
  chatConversations: [],
  chatMessages: [],
  chatLoading: false,
  chatMessagesLoading: false,
  chatSelectedId: null,
  chatDraft: '',
  chatFile: null,
  chatFilePreview: '',
  chatSubscriptionsFor: null,
  chatMessagesSubscriptionId: null,
  chatConversationsUnsubscribe: null,
  chatMessagesUnsubscribe: null,
  roomSearch: '',
  guestSearch: '',
  navOpen: false
};

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function money(value) {
  return `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function prettyDate(value, withYear = true) {
  if (!value) return '—';
  return new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: withYear ? 'numeric' : undefined
  });
}

function monthLabel(date) {
  return date.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
}

function slugToRoom(id) {
  return state.rooms.find((room) => room.id === id) || null;
}

function parseRoute() {
  const hash = location.hash.replace(/^#\/?/, '');
  const [path, param] = hash.split('/');
  if (path === 'book') return { name: 'book', param };
  if (path === 'payment') return { name: 'bookings' };
  if (path === 'login') return { name: 'login' };
  if (path === 'register') return { name: 'register' };
  if (path === 'bookings') return { name: 'bookings' };
  if (path === 'messages') return { name: 'messages', param };
  if (path === 'contact') return { name: 'contact' };
  if (path === 'admin') return { name: 'admin' };
  return { name: 'home' };
}

function navigate(hash) {
  location.hash = hash;
}

function setToast(message, type = 'info') {
  toast.textContent = message;
  toast.className = `toast toast--${type} toast--show`;
  window.clearTimeout(setToast.timer);
  setToast.timer = window.setTimeout(() => {
    toast.className = 'toast';
  }, 4300);
}

function friendlyError(error) {
  const code = error?.code || error?.message || '';
  const messages = {
    EMAIL_EXISTS: 'That email is already registered.',
    INVALID_LOGIN: 'Invalid email or password.',
    LOGIN_REQUIRED: 'Please log in before continuing.',
    DATE_UNAVAILABLE: 'One or more selected nights were just booked. Please choose another range.',
    INVALID_DATES: 'Please choose a valid check-in and check-out range.',
    BOOKING_NOT_FOUND: 'Booking not found or you do not have access to it.',
    CANNOT_CANCEL: 'Only pending bookings can be cancelled.',
    BOOKING_CANCELLED: 'This booking has already been cancelled.',
    MISSING_MESSAGE_FIELDS: 'Please complete all contact fields.',
    CHAT_EMPTY: 'Write a message or choose a picture first.',
    INVALID_IMAGE: 'Please choose an image file.',
    IMAGE_TOO_LARGE: 'Images must be 5 MB or smaller.',
    IMAGE_TOO_LARGE_LOCAL: 'Local demo images must be 1.5 MB or smaller.',
    IMAGE_READ_FAILED: 'The picture could not be read. Please try again.',
    STORAGE_NOT_CONFIGURED: 'Firebase Storage is not configured yet. Add the storageBucket value first.',
    CHAT_NOT_FOUND: 'This conversation is no longer available.',
    ADMIN_REQUIRED: 'Admin access is required for this page.'
  };
  if (messages[code]) return messages[code];
  if (String(code).includes('auth/email-already-in-use')) return messages.EMAIL_EXISTS;
  if (String(code).includes('auth/invalid-credential')) return messages.INVALID_LOGIN;
  if (String(code).includes('permission-denied')) return 'Firebase denied this action. Check Firestore rules and the signed-in user role.';
  return error?.message || 'Something went wrong. Please try again.';
}

function renderHeader() {
  const adminLink = state.user?.role === 'admin'
    ? '<a class="nav-link" href="#/admin">Admin</a>'
    : '';
  const userLinks = state.user
    ? `<a class="nav-link" href="#/bookings">My bookings</a><a class="nav-link" href="#/messages">Messages</a>${adminLink}<button class="nav-link nav-button" data-action="logout">Log out</button>`
    : '<a class="nav-link" href="#/login">Log in</a><a class="button button--small" href="#/register">Create account</a>';
  return `
    <header class="site-header">
      <div class="shell nav-wrap">
        <a class="brand" href="#/" aria-label="Peterlyn's Resort home">
          <span class="brand-mark">P</span>
          <span><strong>Peterlyn's</strong><small>PARADISE RESORT</small></span>
        </a>
        <button class="nav-toggle" type="button" data-action="toggle-nav" aria-expanded="${state.navOpen ? 'true' : 'false'}" aria-label="Menu">Menu</button>
        <nav class="main-nav ${state.navOpen ? 'is-open' : ''}" aria-label="Main navigation">
          <a class="nav-link" href="#/">Home</a>
          <a class="nav-link" href="#rooms">Rooms</a>
          <a class="nav-link" href="#/contact">Contact</a>
          ${userLinks}
        </nav>
      </div>
    </header>`;
}

function renderFooter() {
  return `
    <footer class="site-footer">
      <div class="shell footer-grid">
        <div><div class="footer-brand">Peterlyn's Resort</div><p>Sun, sea, and serenity. Free resort access with every hotel booking.</p></div>
        <div><div class="footer-heading">Explore</div><a href="#/">Home</a><a href="#rooms">Rooms</a><a href="#/contact">Contact</a></div>
        <div><div class="footer-heading">Contact</div><p>Seaside Blvd, PH<br />+63 900 000 0000<br />support@peterlynresort.com</p></div>
      </div>
      <div class="shell footer-bottom">© ${new Date().getFullYear()} Peterlyn's Resort <span>${escapeHtml(firebaseModeLabel())}</span></div>
    </footer>`;
}

function renderRoomCard(room) {
  const image = escapeHtml(assetPath(room.imagePath || 'room/1.jpg'));
  const amenities = (room.amenities || []).slice(0, 3).map((item) => `<span class="tag">${escapeHtml(item)}</span>`).join('');
  return `
    <article class="room-card">
      <div class="room-image-wrap"><img src="${image}" alt="${escapeHtml(room.name)}" loading="lazy" decoding="async" /><span class="room-image-label">Free resort access</span></div>
      <div class="room-card-body">
        <div class="room-card-title"><div><h3>${escapeHtml(room.name)}</h3><p>${escapeHtml(room.view || 'Resort view')} · up to ${room.capacity} guests</p></div><div class="room-price">${money(room.pricePerNight)}<small>/ night</small></div></div>
        <p class="room-description">${escapeHtml(room.description)}</p>
        <div class="tag-list">${amenities}</div>
        <button class="button button--primary button--full" data-room="${escapeHtml(room.id)}">Check dates & book <span>→</span></button>
      </div>
    </article>`;
}

function renderHome() {
  const query = state.roomSearch.trim().toLowerCase();
  const guests = Number(state.guestSearch || 0);
  const rooms = state.rooms.filter((room) => {
    const matchesText = !query || `${room.name} ${room.description} ${(room.amenities || []).join(' ')}`.toLowerCase().includes(query);
    return matchesText && (!guests || room.capacity >= guests);
  });
  return `
    <section class="hero">
      <div class="shell hero-grid">
        <div class="hero-copy"><div class="eyebrow">YOUR ISLAND ESCAPE AWAITS</div><h1>Just experience<br /><em>a new level</em><br />of luxury.</h1><p>Discover sunlit rooms, tranquil spaces, and free access to exclusive resort amenities. Let your next escape begin here.</p><div class="hero-actions"><a class="button button--primary" href="#rooms">Explore rooms <span>↓</span></a><a class="text-link" href="#/contact">Talk to us <span>↗</span></a></div></div>
        <div class="hero-art"><div class="sun-orb"></div><div class="hero-photo"><img src="${assetPath('images/landing.jpg')}" alt="Tropical resort pool" fetchpriority="high" decoding="async" /></div><div class="hero-note"><strong>01</strong><span>Quiet mornings<br />by the sea</span></div></div>
      </div>
      <div class="hero-wave"></div>
    </section>
    <section class="shell intro-section"><div class="eyebrow">STAY A LITTLE LONGER</div><div class="intro-grid"><h2>Rooms designed<br />for <em>slow days.</em></h2><p>From breezy ocean-view suites to private garden villas, every room is made for comfort, connection, and the kind of rest you remember long after checkout.</p></div></section>
    <section id="rooms" class="shell rooms-section">
      <div class="section-heading-row"><div><div class="eyebrow">FIND YOUR STAY</div><h2>Choose your room</h2></div><div class="mode-chip"><span class="status-dot"></span>${escapeHtml(firebaseModeLabel())}</div></div>
      <form class="room-filter" data-form="room-filter"><label><span>Search rooms</span><input name="query" value="${escapeHtml(state.roomSearch)}" placeholder="Ocean, garden, family..." /></label><label><span>Guests</span><input type="number" min="1" name="guests" value="${escapeHtml(state.guestSearch)}" placeholder="Any" /></label><button class="button button--dark" type="submit">Search</button></form>
      ${state.roomsLoading ? '<div class="loading-panel">Loading rooms…</div>' : rooms.length ? `<div class="room-grid">${rooms.map(renderRoomCard).join('')}</div>` : '<div class="empty-panel">No rooms match that search. Try a different keyword or guest count.</div>'}
    </section>
    <section class="shell reassurance-grid"><div><span class="reassurance-icon">✦</span><strong>Free resort access</strong><p>Every booking includes full access to our resort amenities.</p></div><div><span class="reassurance-icon">◷</span><strong>Flexible booking</strong><p>See live date availability before you commit to a room.</p></div><div><span class="reassurance-icon">♡</span><strong>Made for you</strong><p>A calm, personal stay from booking to checkout.</p></div></section>`;
}

function beginningOfMonth(date) {
  return new Date(date.getFullYear(), date.getMonth(), 1);
}

function calendarDays(month) {
  const first = beginningOfMonth(month);
  const start = new Date(first);
  start.setDate(1 - first.getDay());
  return Array.from({ length: 42 }, (_, index) => {
    const day = new Date(start);
    day.setDate(start.getDate() + index);
    return day;
  });
}

function isoDate(date) {
  return localIsoDate(date);
}

function availabilityLookup() {
  const availability = state.availability;
  if (!availability) return { booked: new Set(), blocked: new Set() };
  if (!availability._lookup) {
    const booked = new Set();
    for (const booking of availability.bookings || []) {
      if (!ACTIVE_BOOKING_STATUSES.includes(booking.status)) continue;
      for (const night of enumerateNights(booking.checkIn, booking.checkOut)) booked.add(night);
    }
    const blocked = new Set();
    for (const item of availability.blockedDates || []) {
      for (const night of enumerateNights(item.startDate || item.start_date, item.endDate || item.end_date)) {
        blocked.add(night);
      }
    }
    availability._lookup = { booked, blocked };
  }
  return availability._lookup;
}

function isDateBooked(date) {
  return availabilityLookup().booked.has(date);
}

function isDateBlocked(date) {
  return availabilityLookup().blocked.has(date);
}

function dateOnly(value) {
  return String(value || '').slice(0, 10);
}

function rangeIsOpen(start, end) {
  return enumerateNights(start, end).every((date) => date >= today && !isDateBooked(date) && !isDateBlocked(date));
}

function checkoutCandidate(date) {
  return Boolean(state.booking.checkIn && !state.booking.checkOut && date > state.booking.checkIn && rangeIsOpen(state.booking.checkIn, date));
}

function dateStatus(date) {
  if (date < today) return 'past';
  if (isDateBooked(date)) return 'booked';
  if (isDateBlocked(date)) return 'blocked';
  return 'available';
}

function renderDateBar(day) {
  const date = isoDate(day);
  const status = dateStatus(date);
  const selected = date === state.booking.checkIn || date === state.booking.checkOut;
  const inRange = state.booking.checkIn && state.booking.checkOut && date > state.booking.checkIn && date < state.booking.checkOut;
  const canCheckout = checkoutCandidate(date);
  const disabled = status !== 'available' && !canCheckout && !selected;
  const label = selected ? 'selected' : inRange ? 'in range' : status === 'booked' ? 'booked' : status === 'blocked' ? 'blocked' : status === 'past' ? 'past' : canCheckout ? 'checkout' : 'available';
  const outside = day.getMonth() !== state.calendarMonth.getMonth();
  return `<button class="date-bar date-bar--${status} ${selected ? 'date-bar--selected' : ''} ${inRange ? 'date-bar--range' : ''} ${outside ? 'date-bar--outside' : ''}" data-date="${date}" ${disabled ? 'disabled' : ''} title="${label}"><small>${day.toLocaleDateString('en-PH', { weekday: 'short' })}</small><strong>${day.getDate()}</strong><span>${label}</span></button>`;
}

function renderBooking() {
  const room = state.activeRoom;
  if (!room) return `<div class="shell page-section"><div class="empty-panel">Room not found. <a href="#rooms">Browse rooms</a>.</div></div>`;
  const month = state.calendarMonth;
  const currentMonth = beginningOfMonth(new Date());
  const previousDisabled = month <= currentMonth;
  const nextMonth = new Date(month.getFullYear(), month.getMonth() + 1, 1);
  const loading = state.availabilityLoading && !state.availability;
  const availabilityCopy = state.availability
    ? '<span class="status-dot status-dot--green"></span>Live availability — red dates cannot be booked'
    : 'Loading live availability…';
  const nights = state.booking.checkIn && state.booking.checkOut ? countNights(state.booking.checkIn, state.booking.checkOut) : 0;
  const total = nights * room.pricePerNight;
  return `<section class="shell page-section booking-page">
    <a class="back-link" href="#rooms">← Back to rooms</a>
    <div class="booking-layout">
      <div class="booking-room-card"><img src="${escapeHtml(assetPath(room.imagePath))}" alt="${escapeHtml(room.name)}" /><div class="booking-room-content"><div class="eyebrow">YOUR SELECTED ROOM</div><h1>${escapeHtml(room.name)}</h1><p>${escapeHtml(room.description)}</p><div class="room-meta"><span>◉ Up to ${room.capacity} guests</span><span>◌ ${escapeHtml(room.view || 'Resort view')}</span><span>▱ ${room.sizeSqm || '—'} sqm</span></div><div class="booking-price">${money(room.pricePerNight)} <small>/ night</small></div></div></div>
      <div class="booking-panel"><div class="panel-heading"><div><div class="eyebrow">STEP 1 OF 2</div><h2>Select your dates</h2></div><div class="availability-copy">${availabilityCopy}</div></div>
        <div class="calendar-toolbar"><button class="icon-button" data-action="change-month" data-direction="-1" ${previousDisabled ? 'disabled' : ''} aria-label="Previous month">←</button><strong>${monthLabel(month)}</strong><button class="icon-button" data-action="change-month" data-direction="1" aria-label="Next month">→</button></div>
        <div class="calendar-weekdays">${['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => `<span>${day}</span>`).join('')}</div>
        <div class="date-bars">${loading ? '<div class="calendar-loading">Checking booked dates…</div>' : calendarDays(month).map(renderDateBar).join('')}</div>
        <div class="calendar-legend"><span><i class="legend-dot legend-dot--available"></i>Available</span><span><i class="legend-dot legend-dot--booked"></i>Booked</span><span><i class="legend-dot legend-dot--blocked"></i>Past / blocked</span><span><i class="legend-dot legend-dot--selected"></i>Selected</span></div>
        <form class="booking-form" data-form="booking"><div class="selected-summary"><div><span>Check-in</span><strong>${prettyDate(state.booking.checkIn)}</strong></div><div class="summary-arrow">→</div><div><span>Check-out</span><strong>${prettyDate(state.booking.checkOut)}</strong></div><div class="summary-total"><span>${nights ? `${nights} night${nights === 1 ? '' : 's'}` : 'Choose dates'}</span><strong>${nights ? money(total) : '—'}</strong></div></div><label><span>Guests</span><input type="number" name="guests" min="1" max="${room.capacity}" value="${state.booking.guests}" required /></label>${state.user ? `<button class="button button--primary button--full" type="submit" ${!state.booking.checkIn || !state.booking.checkOut || loading ? 'disabled' : ''}>Request booking <span>→</span></button>` : '<a class="button button--primary button--full" href="#/login">Log in to continue <span>→</span></a>'}<p class="form-help">Your dates are protected when you submit. Pending bookings wait for resort approval and can be cancelled before approval.</p></form>
      </div>
    </div>
  </section>`;
}

function renderAuth(mode) {
  const login = mode === 'login';
  return `<section class="shell page-section narrow-page"><div class="auth-card"><div class="eyebrow">PETERLYN'S RESORT</div><h1>${login ? 'Welcome back.' : 'Begin your escape.'}</h1><p>${login ? 'Log in to manage your stays and book your next quiet morning.' : 'Create an account to see live availability and keep your bookings in one place.'}</p><form data-form="${login ? 'login' : 'register'}">${login ? '' : '<label><span>Full name</span><input name="fullName" autocomplete="name" required /></label>'}<label><span>Email</span><input type="email" name="email" autocomplete="email" required /></label><label><span>Password</span><input type="password" name="password" autocomplete="${login ? 'current-password' : 'new-password'}" minlength="6" required /></label>${login ? '' : '<label><span>Confirm password</span><input type="password" name="confirmPassword" autocomplete="new-password" minlength="6" required /></label>'}<button class="button button--primary button--full" type="submit">${login ? 'Log in' : 'Create account'} <span>→</span></button></form><div class="auth-switch">${login ? "Don't have an account?" : 'Already have an account?'} <a href="#/${login ? 'register' : 'login'}">${login ? 'Create one' : 'Log in'}</a></div><div class="demo-hint">Local demo access: <strong>admin@paradise.local</strong> / <strong>admin123</strong></div></div></section>`;
}

function statusClass(status) {
  return `status-pill status-pill--${String(status || '').replace('_', '-')}`;
}

/* Legacy payment UI kept out of the active booking-only flow. */
/*
function renderBookingsLegacy() {
  if (!state.user) return `<div class="shell page-section"><div class="empty-panel">Please <a href="#/login">log in</a> to see your bookings.</div></div>`;
  if (state.bookingsLoading) return '<div class="shell page-section"><div class="loading-panel">Loading your bookings…</div></div>';
  return `<section class="shell page-section"><div class="section-heading-row"><div><div class="eyebrow">YOUR ESCAPE, ORGANIZED</div><h1>My bookings</h1></div><a class="button button--dark" href="#rooms">Book another stay <span>→</span></a></div>${state.bookings.length ? `<div class="booking-list">${state.bookings.map((booking) => { const room = booking.room || {}; return `<article class="booking-card"><img src="${escapeHtml(assetPath(booking.roomImagePath || room.imagePath || 'room/1.jpg'))}" alt="${escapeHtml(booking.roomName || room.name || 'Room')}" /><div class="booking-card-body"><div class="booking-card-heading"><div><h3>${escapeHtml(booking.roomName || room.name || 'Room')}</h3><p>${prettyDate(booking.checkIn)} → ${prettyDate(booking.checkOut)} · ${booking.guests} guest${booking.guests === 1 ? '' : 's'}</p></div><span class="${statusClass(booking.status)}">${escapeHtml(booking.status)}</span></div><div class="booking-card-footer"><span>${booking.paymentStatus === 'paid' ? 'Paid' : 'Payment pending'} · ${money(booking.total)}</span><div class="inline-actions">${booking.status === 'pending' && booking.paymentStatus !== 'paid' ? `<a class="button button--primary button--small" href="#/payment/${booking.id}">Pay now</a><button class="text-button" data-cancel-booking="${escapeHtml(booking.id)}">Cancel</button>` : ''}</div></div></div></article>`; }).join('')}</div>` : '<div class="empty-panel">No bookings yet. Your next quiet morning starts with a room. <a href="#rooms">Browse rooms</a>.</div>'}</section>`;
}

function renderPaymentLegacy(booking) {
  if (!booking) return '<div class="shell page-section"><div class="empty-panel">Booking not found. <a href="#/bookings">Back to bookings</a>.</div></div>';
  const room = booking.room || {};
  return `<section class="shell page-section narrow-page"><div class="payment-card"><div class="eyebrow">STEP 2 OF 2</div><h1>Confirm your stay.</h1><p>Complete the demo payment below to mark this booking as approved. Replace this simulation with your preferred payment gateway before going live.</p><div class="payment-summary"><img src="${escapeHtml(assetPath(booking.roomImagePath || room.imagePath || 'room/1.jpg'))}" alt="${escapeHtml(booking.roomName || room.name || 'Room')}" /><div><strong>${escapeHtml(booking.roomName || room.name || 'Room')}</strong><span>${prettyDate(booking.checkIn)} → ${prettyDate(booking.checkOut)}</span><span>${countNights(booking.checkIn, booking.checkOut)} night${countNights(booking.checkIn, booking.checkOut) === 1 ? '' : 's'}</span><b>${money(booking.total)}</b></div></div><form data-form="payment"><label><span>Payment method</span><select name="method"><option value="card">Credit / debit card</option><option value="gcash">GCash</option><option value="maya">Maya</option></select></label><label><span>Payment detail</span><input name="detail" inputmode="numeric" placeholder="Enter a test card or wallet number" required /></label><button class="button button--primary button--full" type="submit">Pay ${money(booking.total)} <span>→</span></button></form><a class="back-link" href="#/bookings">← Back to my bookings</a></div></section>`;
}

*/
function renderBookings() {
  if (!state.user) return `<div class="shell page-section"><div class="empty-panel">Please <a href="#/login">log in</a> to see your bookings.</div></div>`;
  if (state.bookingsLoading) return '<div class="shell page-section"><div class="loading-panel">Loading your bookings…</div></div>';
  const cards = state.bookings.map((booking) => {
    const room = booking.room || {};
    const statusLabel = booking.status === 'approved' ? 'Confirmed' : booking.status === 'pending' ? 'Booking request' : booking.status;
    const canCancel = booking.status === 'pending';
    return `<article class="booking-card"><img src="${escapeHtml(assetPath(booking.roomImagePath || room.imagePath || 'room/1.jpg'))}" alt="${escapeHtml(booking.roomName || room.name || 'Room')}" /><div class="booking-card-body"><div class="booking-card-heading"><div><h3>${escapeHtml(booking.roomName || room.name || 'Room')}</h3><p>${prettyDate(booking.checkIn)} → ${prettyDate(booking.checkOut)} · ${booking.guests} guest${booking.guests === 1 ? '' : 's'}</p></div><span class="${statusClass(booking.status)}">${escapeHtml(statusLabel)}</span></div><div class="booking-card-footer"><span>${escapeHtml(statusLabel)} · ${money(booking.total)}</span><div class="inline-actions">${canCancel ? `<button class="text-button" data-cancel-booking="${escapeHtml(booking.id)}">Cancel</button>` : ''}</div></div></div></article>`;
  }).join('');
  return `<section class="shell page-section"><div class="section-heading-row"><div><div class="eyebrow">YOUR ESCAPE, ORGANIZED</div><h1>My bookings</h1></div><a class="button button--dark" href="#rooms">Book another stay <span>→</span></a></div>${state.bookings.length ? `<div class="booking-list">${cards}</div>` : '<div class="empty-panel">No bookings yet. Your next quiet morning starts with a room. <a href="#rooms">Browse rooms</a>.</div>'}</section>`;
}

function chatTime(value) {
  if (!value) return '';
  return new Date(value).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
}

function chatConversationName(conversation, isAdmin) {
  if (!isAdmin) return "Peterlyn's Resort team";
  return conversation?.customerName || conversation?.customerEmail || 'Customer';
}

function renderChatMessage(message) {
  const own = message.senderUid === state.user?.uid;
  const text = message.text ? `<div class="chat-message-text">${escapeHtml(message.text).split(String.fromCharCode(10)).join('<br />')}</div>` : '';
  const image = message.imageUrl
    ? `<a class="chat-image-link" href="${escapeHtml(message.imageUrl)}" target="_blank" rel="noopener"><img class="chat-image" src="${escapeHtml(message.imageUrl)}" alt="${escapeHtml(message.imageName || 'Shared picture')}" loading="lazy" /></a>`
    : '';
  return `<div class="chat-message-row ${own ? 'chat-message-row--own' : ''}"><div class="chat-message-bubble">${image}${text}<div class="chat-message-meta">${own ? 'You' : escapeHtml(message.senderName || 'User')} · ${chatTime(message.createdAt)}</div></div></div>`;
}

function renderMessages() {
  if (!state.user) return '<div class="shell page-section"><div class="empty-panel">Please <a href="#/login">log in</a> to open messages.</div></div>';

  const isAdmin = state.user.role === 'admin';
  const customerConversation = { id: state.user.uid, customerName: "Peterlyn's Resort team", customerEmail: '' };
  const conversations = isAdmin ? state.chatConversations : [customerConversation];
  const routeConversationId = state.route.param;
  const selectedId = isAdmin
    ? (routeConversationId || state.chatSelectedId || conversations[0]?.id || '')
    : state.user.uid;
  const selectedConversation = conversations.find((item) => item.id === selectedId) || (isAdmin ? null : customerConversation);
  state.chatSelectedId = selectedConversation?.id || null;
  const conversationList = conversations.length
    ? conversations.map((conversation) => {
      const active = conversation.id === selectedConversation?.id;
      const preview = conversation.lastMessage || (isAdmin ? 'Start a conversation' : 'Ask us anything about your stay');
      return `<button type="button" class="chat-conversation ${active ? 'is-active' : ''}" data-chat-conversation="${escapeHtml(conversation.id)}"><span class="chat-avatar">${escapeHtml(chatConversationName(conversation, isAdmin).slice(0, 1).toUpperCase())}</span><span class="chat-conversation-copy"><strong>${escapeHtml(chatConversationName(conversation, isAdmin))}</strong><small>${escapeHtml(preview)}</small></span>${conversation.lastMessageAt ? `<time>${chatTime(conversation.lastMessageAt)}</time>` : ''}</button>`;
    }).join('')
    : '<div class="chat-list-empty">No customer conversations yet.</div>';

  const thread = selectedConversation
    ? `<div class="chat-thread-header"><div><div class="eyebrow">${isAdmin ? 'CUSTOMER CONVERSATION' : 'RESORT SUPPORT'}</div><h2>${escapeHtml(chatConversationName(selectedConversation, isAdmin))}</h2><p>${isAdmin ? escapeHtml(selectedConversation.customerEmail || 'Send a message to this customer.') : 'We usually reply as soon as possible.'}</p></div><span class="chat-online"><span></span>${isAdmin ? 'Support inbox' : 'Online'}</span></div><div class="chat-messages" aria-live="polite">${state.chatMessagesLoading ? '<div class="chat-thread-empty">Loading messages…</div>' : state.chatMessages.length ? state.chatMessages.map(renderChatMessage).join('') : '<div class="chat-thread-empty"><strong>Start the conversation</strong><span>Send a message or share a picture with the resort team.</span></div>'}</div><form class="chat-composer" data-form="chat-message"><div class="chat-attachment-preview ${state.chatFilePreview ? 'is-visible' : ''}">${state.chatFilePreview ? `<img src="${escapeHtml(state.chatFilePreview)}" alt="Selected picture" /><span>${escapeHtml(state.chatFile?.name || 'Picture selected')}</span><button type="button" class="chat-remove-file" data-action="remove-chat-file" aria-label="Remove selected picture">×</button>` : ''}</div><textarea name="message" data-chat-draft rows="1" maxlength="2000" placeholder="Write a message…">${escapeHtml(state.chatDraft || '')}</textarea><div class="chat-composer-footer"><label class="chat-attach-button"><input type="file" name="image" accept="image/*" data-chat-file /><span>＋</span>Photo</label><span class="chat-composer-hint">Images up to 5 MB</span><button class="button button--primary chat-send-button" type="submit">Send <span>→</span></button></div></form>`
    : '<div class="chat-no-selection"><strong>Select a conversation</strong><span>Customer messages will appear here.</span></div>';

  return `<section class="shell page-section messages-page"><div class="section-heading-row messages-heading"><div><div class="eyebrow">DIRECT SUPPORT</div><h1>Messages</h1><p class="section-subtitle">Chat with the resort team and share booking details or pictures.</p></div><div class="mode-chip"><span class="status-dot"></span>${isAdmin ? 'Customer inbox' : 'Resort support'}</div></div><div class="messenger"><aside class="chat-sidebar"><div class="chat-sidebar-heading"><strong>${isAdmin ? 'Conversations' : 'Support'}</strong><span>${conversations.length}</span></div><div class="chat-conversation-list">${conversationList}</div></aside><section class="chat-panel">${thread}</section></div></section>`;
}

function renderContact() {
  return `<section class="shell page-section contact-page"><div class="contact-intro"><div class="eyebrow">WE'D LOVE TO HEAR FROM YOU</div><h1>Let's make your<br /><em>stay memorable.</em></h1><p>Questions about rooms, dates, or resort access? Send a note and our team will get back to you.</p><div class="contact-detail"><strong>Seaside Blvd, PH</strong><span>+63 900 000 0000</span><span>support@peterlynresort.com</span></div></div><div class="contact-card"><form data-form="contact"><label><span>Your name</span><input name="name" value="${escapeHtml(state.user?.fullName || '')}" required /></label><label><span>Email</span><input type="email" name="email" value="${escapeHtml(state.user?.email || '')}" required /></label><label><span>Message</span><textarea name="message" rows="6" required placeholder="Tell us how we can help..."></textarea></label><button class="button button--primary button--full" type="submit">Send message <span>→</span></button></form></div></section>`;
}

function renderAdmin() {
  if (!state.user || state.user.role !== 'admin') return '<div class="shell page-section"><div class="empty-panel">Admin access is required. <a href="#/login">Log in</a>.</div></div>';
  if (state.adminLoading) return '<div class="shell page-section"><div class="loading-panel">Loading admin bookings…</div></div>';
  const rows = state.adminBookings.map((booking) => `<tr><td><strong>${escapeHtml(booking.email || booking.uid || 'Guest')}</strong><small>${escapeHtml(booking.uid || '')}</small></td><td>${escapeHtml(booking.roomName || booking.room?.name || booking.roomId)}</td><td>${prettyDate(booking.checkIn)}<br />→ ${prettyDate(booking.checkOut)}</td><td>${money(booking.total)}</td><td><span class="${statusClass(booking.status)}">${escapeHtml(booking.status)}</span></td><td><select data-admin-status="${escapeHtml(booking.id)}"><option value="">Update…</option><option value="approved">Approve</option><option value="rejected">Reject</option><option value="cancelled">Cancel</option><option value="checked_in">Check in</option><option value="completed">Complete</option></select></td></tr>`).join('');
  return `<section class="shell page-section"><div class="section-heading-row"><div><div class="eyebrow">RESORT OPERATIONS</div><h1>Booking calendar</h1><p class="section-subtitle">Approve, reject, or cancel reservations. Rejected and cancelled bookings release their locked nights.</p></div><div class="mode-chip"><span class="status-dot"></span>${state.adminBookings.length} total</div></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Guest</th><th>Room</th><th>Dates</th><th>Total</th><th>Status</th><th>Action</th></tr></thead><tbody>${rows || '<tr><td colspan="6" class="empty-cell">No bookings yet.</td></tr>'}</tbody></table></div></section>`;
}

/*
function renderAdminLegacy() {
  if (!state.user || state.user.role !== 'admin') return '<div class="shell page-section"><div class="empty-panel">Admin access is required. <a href="#/login">Log in</a>.</div></div>';
  if (state.adminLoading) return '<div class="shell page-section"><div class="loading-panel">Loading admin bookings…</div></div>';
  return `<section class="shell page-section"><div class="section-heading-row"><div><div class="eyebrow">RESORT OPERATIONS</div><h1>Booking calendar</h1><p class="section-subtitle">Approve, reject, or cancel reservations. Rejected and cancelled bookings release their locked nights.</p></div><div class="mode-chip"><span class="status-dot"></span>${state.adminBookings.length} total</div></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Guest</th><th>Room</th><th>Dates</th><th>Payment</th><th>Status</th><th>Action</th></tr></thead><tbody>${state.adminBookings.length ? state.adminBookings.map((booking) => `<tr><td><strong>${escapeHtml(booking.email || booking.uid || 'Guest')}</strong><small>${escapeHtml(booking.uid || '')}</small></td><td>${escapeHtml(booking.roomName || booking.room?.name || booking.roomId)}</td><td>${prettyDate(booking.checkIn)}<br />→ ${prettyDate(booking.checkOut)}</td><td>${booking.paymentStatus === 'paid' ? '<span class="paid-text">Paid</span>' : 'Unpaid'}<br />${money(booking.total)}</td><td><span class="${statusClass(booking.status)}">${escapeHtml(booking.status)}</span></td><td><select data-admin-status="${escapeHtml(booking.id)}"><option value="">Update…</option><option value="approved">Approve</option><option value="rejected">Reject</option><option value="cancelled">Cancel</option><option value="checked_in">Check in</option><option value="completed">Complete</option></select></td></tr>`).join('') : '<tr><td colspan="6" class="empty-cell">No bookings yet.</td></tr>'}</tbody></table></div></section>`;
}

*/
function renderMain() {
  state.route = parseRoute();
  if (state.route.name === 'book') return renderBooking();
  if (state.route.name === 'login' || state.route.name === 'register') return renderAuth(state.route.name);
  if (state.route.name === 'bookings') return renderBookings();
  if (state.route.name === 'messages') return renderMessages();
  if (state.route.name === 'contact') return renderContact();
  if (state.route.name === 'admin') return renderAdmin();
  return renderHome();
}

function stopChatMessageSubscription() {
  if (typeof state.chatMessagesUnsubscribe === 'function') state.chatMessagesUnsubscribe();
  state.chatMessagesUnsubscribe = null;
  state.chatMessagesSubscriptionId = null;
  state.chatMessages = [];
  state.chatMessagesLoading = false;
}

function stopChatSubscriptions() {
  if (typeof state.chatConversationsUnsubscribe === 'function') state.chatConversationsUnsubscribe();
  state.chatConversationsUnsubscribe = null;
  state.chatSubscriptionsFor = null;
  stopChatMessageSubscription();
  state.chatConversations = [];
  state.chatSelectedId = null;
  state.chatLoading = false;
}

function startChatSubscriptions() {
  if (!state.user || state.route.name !== 'messages') return;
  if (state.chatSubscriptionsFor !== state.user.uid) {
    stopChatSubscriptions();
    state.chatSubscriptionsFor = state.user.uid;
    state.chatLoading = true;
    state.chatConversationsUnsubscribe = observeChatConversations(state.user, (conversations) => {
      state.chatConversations = conversations;
      state.chatLoading = false;
      const routeId = state.route?.param;
      const firstId = conversations[0]?.id || (state.user.role === 'customer' ? state.user.uid : null);
      state.chatSelectedId = routeId || state.chatSelectedId || firstId;
      render();
    });
  }

  const selectedId = state.route.param || state.chatSelectedId || (state.user.role === 'customer' ? state.user.uid : null);
  if (!selectedId || state.chatMessagesSubscriptionId === selectedId) return;
  stopChatMessageSubscription();
  state.chatMessagesSubscriptionId = selectedId;
  state.chatMessagesLoading = true;
  state.chatMessagesUnsubscribe = observeChatMessages(state.user, selectedId, (messages) => {
    state.chatMessages = messages;
    state.chatMessagesLoading = false;
    render();
    const container = document.querySelector('.chat-messages');
    if (container) container.scrollTop = container.scrollHeight;
  });
}

function render() {
  const parsedRoute = parseRoute();
  if (parsedRoute.name === 'book' && (!state.activeRoom || state.activeRoom.id !== parsedRoute.param)) {
    state.activeRoom = slugToRoom(parsedRoute.param);
  }
  state.route = parsedRoute;
  app.innerHTML = `${renderHeader()}<main>${renderMain()}</main>${renderFooter()}`;
  document.body.classList.toggle('has-dark-page', state.route.name === 'book');
  if (state.route.name === 'book' && state.activeRoom && state.availabilityAttemptedFor !== state.activeRoom.id && !state.availabilityLoading) loadAvailability(state.activeRoom.id);
  if (state.route.name === 'bookings' && state.user && state.bookingsLoadedFor !== state.user.uid && !state.bookingsLoading) loadBookings();
  if (state.route.name === 'admin' && state.user?.role === 'admin' && state.adminLoadedFor !== state.user.uid && !state.adminLoading) loadAdminBookings();
  if (state.route.name === 'messages' && state.user) startChatSubscriptions();
}

async function loadRooms() {
  try {
    state.rooms = await getRooms();
  } catch (error) {
    setToast(friendlyError(error), 'error');
  } finally {
    state.roomsLoading = false;
    render();
  }
}

async function loadAvailability(roomId) {
  state.availabilityAttemptedFor = roomId;
  state.availabilityError = null;
  state.availabilityLoading = true;
  try {
    state.availability = await getRoomAvailability(roomId);
    state.availabilityRoom = roomId;
  } catch (error) {
    state.availabilityError = friendlyError(error);
    setToast(friendlyError(error), 'error');
  } finally {
    state.availabilityLoading = false;
    render();
  }
}

async function loadBookings() {
  state.bookingsLoadedFor = state.user.uid;
  state.bookingsLoading = true;
  render();
  try {
    state.bookings = await getMyBookings(state.user);
  } catch (error) {
    setToast(friendlyError(error), 'error');
  } finally {
    state.bookingsLoading = false;
    render();
  }
}

async function loadAdminBookings() {
  state.adminLoadedFor = state.user.uid;
  state.adminLoading = true;
  render();
  try {
    state.adminBookings = await getAllBookings(state.user);
  } catch (error) {
    setToast(friendlyError(error), 'error');
  } finally {
    state.adminLoading = false;
    render();
  }
}

function resetBooking(roomId) {
  state.activeRoom = slugToRoom(roomId);
  state.availability = null;
  state.availabilityRoom = null;
  state.availabilityAttemptedFor = null;
  state.availabilityError = null;
  state.booking = { checkIn: '', checkOut: '', guests: 1 };
  state.calendarMonth = beginningOfMonth(new Date());
}

async function handleSubmit(event) {
  const form = event.target;
  if (!form.matches('form[data-form]')) return;
  event.preventDefault();
  const formData = new FormData(form);
  const submit = form.querySelector('button[type="submit"]');
  if (submit) submit.disabled = true;
  try {
    if (form.dataset.form === 'room-filter') {
      state.roomSearch = String(formData.get('query') || '');
      state.guestSearch = String(formData.get('guests') || '');
      render();
      return;
    }
    if (form.dataset.form === 'login') {
      state.user = await signIn(String(formData.get('email')), String(formData.get('password')));
      setToast('Welcome back.', 'success');
      navigate('#/');
      return;
    }
    if (form.dataset.form === 'register') {
      if (formData.get('password') !== formData.get('confirmPassword')) throw new Error('Passwords do not match.');
      state.user = await registerUser(String(formData.get('fullName')), String(formData.get('email')), String(formData.get('password')));
      setToast('Account created. You are now logged in.', 'success');
      navigate('#/');
      return;
    }
    if (form.dataset.form === 'booking') {
      if (!state.activeRoom || !state.booking.checkIn || !state.booking.checkOut) throw new Error('Please select check-in and check-out dates.');
      if (state.availabilityError) throw new Error('Availability could not be loaded. Please refresh and try again.');
      state.booking.guests = Number(formData.get('guests'));
      if (!state.booking.guests || state.booking.guests > state.activeRoom.capacity) throw new Error(`This room accepts up to ${state.activeRoom.capacity} guests.`);
      if (!rangeIsOpen(state.booking.checkIn, state.booking.checkOut)) throw new Error('One or more selected nights are not available.');
      await createBooking({ user: state.user, room: state.activeRoom, ...state.booking });
      state.bookings = [];
      state.bookingsLoadedFor = null;
      setToast('Booking request submitted.', 'success');
      navigate('#/bookings');
      return;
    }
    if (form.dataset.form === 'chat-message') {
      const conversationId = state.chatSelectedId || state.route.param || state.user.uid;
      const selectedFile = formData.get('image');
      const file = selectedFile?.size ? selectedFile : state.chatFile;
      const sentMessage = await sendChatMessage(state.user, conversationId, formData.get('message'), file);
      state.chatMessages = [...state.chatMessages.filter((message) => message.id !== sentMessage.id), sentMessage];
      state.chatDraft = '';
      state.chatFile = null;
      state.chatFilePreview = '';
      setToast('Message sent.', 'success');
      render();
      const container = document.querySelector('.chat-messages');
      if (container) container.scrollTop = container.scrollHeight;
      return;
    }
    if (form.dataset.form === 'contact') {
      await sendContactMessage(state.user, String(formData.get('name')), String(formData.get('email')), String(formData.get('message')));
      form.reset();
      setToast('Message sent. We will be in touch soon.', 'success');
    }
  } catch (error) {
    setToast(friendlyError(error), 'error');
  } finally {
    if (submit) submit.disabled = false;
  }
}

function handleInput(event) {
  if (event.target.matches('[data-chat-draft]')) state.chatDraft = event.target.value;
}

function handleChange(event) {
  const input = event.target;
  if (!input.matches('[data-chat-file]')) return;
  const file = input.files?.[0];
  if (!file) return;
  if (!String(file.type || '').startsWith('image/')) {
    setToast('Please choose an image file.', 'error');
    input.value = '';
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    setToast('Images must be 5 MB or smaller.', 'error');
    input.value = '';
    return;
  }
  if (state.chatFilePreview?.startsWith('blob:')) URL.revokeObjectURL(state.chatFilePreview);
  state.chatFile = file;
  state.chatFilePreview = URL.createObjectURL(file);
  render();
}

async function handleClick(event) {
  const chatConversation = event.target.closest('[data-chat-conversation]');
  if (chatConversation) {
    state.chatSelectedId = chatConversation.dataset.chatConversation;
    navigate(`#/messages/${state.chatSelectedId}`);
    return;
  }
  const removeChatFile = event.target.closest('[data-action="remove-chat-file"]');
  if (removeChatFile) {
    if (state.chatFilePreview?.startsWith('blob:')) URL.revokeObjectURL(state.chatFilePreview);
    state.chatFile = null;
    state.chatFilePreview = '';
    render();
    return;
  }
  const roomButton = event.target.closest('[data-room]');
  if (roomButton) {
    resetBooking(roomButton.dataset.room);
    navigate(`#/book/${roomButton.dataset.room}`);
    return;
  }
  const dateButton = event.target.closest('[data-date]');
  if (dateButton && !dateButton.disabled) {
    const date = dateButton.dataset.date;
    if (!state.booking.checkIn || state.booking.checkOut || date <= state.booking.checkIn) {
      state.booking.checkIn = date;
      state.booking.checkOut = '';
    } else if (rangeIsOpen(state.booking.checkIn, date)) {
      state.booking.checkOut = date;
    } else {
      setToast('That range contains a booked or blocked night.', 'error');
    }
    render();
    return;
  }
  const monthButton = event.target.closest('[data-action="change-month"]');
  if (monthButton) {
    const direction = Number(monthButton.dataset.direction);
    state.calendarMonth = new Date(state.calendarMonth.getFullYear(), state.calendarMonth.getMonth() + direction, 1);
    render();
    return;
  }
  const navToggle = event.target.closest('[data-action="toggle-nav"]');
  if (navToggle) {
    state.navOpen = !state.navOpen;
    render();
    return;
  }
  const navLink = event.target.closest('.main-nav a, .main-nav button');
  if (navLink && state.navOpen && !navLink.matches('[data-action="toggle-nav"]')) {
    state.navOpen = false;
  }
  const logoutButton = event.target.closest('[data-action="logout"]');
  if (logoutButton) {
    stopChatSubscriptions();
    await signOut();
    state.user = null;
    state.bookings = [];
    state.adminBookings = [];
    state.bookingsLoadedFor = null;
    state.adminLoadedFor = null;
    setToast('You have been logged out.', 'success');
    navigate('#/');
    return;
  }
  const cancelButton = event.target.closest('[data-cancel-booking]');
  if (cancelButton) {
    if (!window.confirm('Cancel this unpaid booking and release its dates?')) return;
    try {
      await cancelBooking(cancelButton.dataset.cancelBooking, state.user);
      state.bookings = [];
      state.bookingsLoadedFor = null;
      setToast('Booking cancelled and dates released.', 'success');
      render();
    } catch (error) {
      setToast(friendlyError(error), 'error');
    }
    return;
  }
  const statusSelect = event.target.closest('[data-admin-status]');
  if (statusSelect && statusSelect.value) {
    try {
      await updateBookingStatus(statusSelect.dataset.adminStatus, statusSelect.value, state.user);
      state.adminBookings = [];
      state.adminLoadedFor = null;
      setToast('Booking status updated.', 'success');
      render();
    } catch (error) {
      setToast(friendlyError(error), 'error');
    }
  }
}

app.addEventListener('submit', handleSubmit);
app.addEventListener('input', handleInput);
app.addEventListener('change', handleChange);
app.addEventListener('click', handleClick);
window.addEventListener('hashchange', () => {
  const route = parseRoute();
  state.navOpen = false;
  if (route.name !== 'bookings') {
    state.bookings = [];
    state.bookingsLoadedFor = null;
  }
  if (route.name !== 'admin') {
    state.adminBookings = [];
    state.adminLoadedFor = null;
  }
  if (route.name !== 'messages') {
    stopChatSubscriptions();
    state.chatDraft = '';
    state.chatFile = null;
    state.chatFilePreview = '';
  }
  render();
});

observeUser((user) => {
  stopChatSubscriptions();
  state.user = user;
  if (state.route?.name === 'bookings' || state.route?.name === 'admin') {
    state.bookings = [];
    state.adminBookings = [];
    state.bookingsLoadedFor = null;
    state.adminLoadedFor = null;
  }
  render();
});

render();
loadRooms();
