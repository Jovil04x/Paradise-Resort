import { firebaseConfig, hasFirebaseConfig } from './firebase-config.js';

// Only load Firebase over the network when a real Firebase configuration exists.
// The localStorage demo mode must also work on offline/local-only installations.
let initializeApp;
let createUserWithEmailAndPassword;
let getAuth;
let onAuthStateChanged;
let signInWithEmailAndPassword;
let firebaseSignOut;
let updateProfile;
let addDoc;
let collection;
let deleteDoc;
let doc;
let getDoc;
let getDocs;
let getFirestore;
let query;
let runTransaction;
let serverTimestamp;
let setDoc;
let updateDoc;
let where;

if (hasFirebaseConfig) {
  const [appModule, authModule, firestoreModule] = await Promise.all([
    import('https://www.gstatic.com/firebasejs/12.19.0/firebase-app.js'),
    import('https://www.gstatic.com/firebasejs/12.19.0/firebase-auth.js'),
    import('https://www.gstatic.com/firebasejs/12.19.0/firebase-firestore.js')
  ]);

  ({ initializeApp } = appModule);
  ({
    createUserWithEmailAndPassword,
    getAuth,
    onAuthStateChanged,
    signInWithEmailAndPassword,
    signOut: firebaseSignOut,
    updateProfile
  } = authModule);
  ({
    addDoc,
    collection,
    deleteDoc,
    doc,
    getDoc,
    getDocs,
    getFirestore,
    query,
    runTransaction,
    serverTimestamp,
    setDoc,
    updateDoc,
    where
  } = firestoreModule);
}

export const firebaseEnabled = hasFirebaseConfig;
export const ACTIVE_BOOKING_STATUSES = ['pending', 'approved', 'checked_in'];

export const DEFAULT_ROOMS = [
  {
    id: 'ocean-view-suite',
    name: 'Ocean View Suite',
    description: 'Spacious suite with panoramic ocean views and modern interiors. Booking includes free resort access.',
    pricePerNight: 199,
    capacity: 3,
    imagePath: 'room/1.jpg',
    amenities: ['Wi-Fi', 'Pool', 'Air conditioning', 'Breakfast'],
    bedType: 'King bed',
    sizeSqm: 42,
    view: 'Ocean view',
    isActive: true
  },
  {
    id: 'garden-villa',
    name: 'Garden Villa',
    description: 'Private villa surrounded by lush gardens, perfect for a tranquil retreat. Free resort access included.',
    pricePerNight: 249,
    capacity: 4,
    imagePath: 'room/2.jpg',
    amenities: ['Wi-Fi', 'Private garden', 'Air conditioning', 'Breakfast'],
    bedType: 'Queen beds',
    sizeSqm: 55,
    view: 'Garden view',
    isActive: true
  },
  {
    id: 'deluxe-king-room',
    name: 'Deluxe King Room',
    description: 'Modern room with king bed, warm tones, and sleek finishes. Complimentary resort access.',
    pricePerNight: 149,
    capacity: 2,
    imagePath: 'room/3.jpg',
    amenities: ['Wi-Fi', 'Pool', 'Air conditioning', 'Breakfast'],
    bedType: 'King bed',
    sizeSqm: 32,
    view: 'Resort view',
    isActive: true
  },
  {
    id: 'family-suite',
    name: 'Family Suite',
    description: 'Comfortable suite ideal for families, featuring extra space and amenities. Free resort access included.',
    pricePerNight: 279,
    capacity: 5,
    imagePath: 'room/4.jpg',
    amenities: ['Wi-Fi', 'Pool', 'Kitchenette', 'Breakfast'],
    bedType: 'Two queen beds',
    sizeSqm: 68,
    view: 'Pool view',
    isActive: true
  }
];

const LOCAL_KEYS = {
  users: 'paradise.firebase.local.users',
  bookings: 'paradise.firebase.local.bookings',
  messages: 'paradise.firebase.local.messages',
  user: 'paradise.firebase.local.currentUser'
};

const firebaseApp = firebaseEnabled ? initializeApp(firebaseConfig) : null;
const auth = firebaseApp ? getAuth(firebaseApp) : null;
const firestore = firebaseApp ? getFirestore(firebaseApp) : null;
const ROOMS_TTL_MS = 60_000;
let roomsCache = { expires: 0, rooms: null };

function readLocal(key, fallback) {
  try {
    const value = JSON.parse(localStorage.getItem(key) || 'null');
    return value === null ? fallback : value;
  } catch {
    return fallback;
  }
}

function writeLocal(key, value) {
  localStorage.setItem(key, JSON.stringify(value));
}

function localId(prefix) {
  if (globalThis.crypto?.randomUUID) return `${prefix}_${crypto.randomUUID()}`;
  return `${prefix}_${Date.now()}_${Math.random().toString(16).slice(2)}`;
}

function nowIso() {
  return new Date().toISOString();
}

function timestampToIso(value) {
  if (!value) return nowIso();
  if (typeof value === 'string') return value;
  if (typeof value.toDate === 'function') return value.toDate().toISOString();
  if (typeof value.seconds === 'number') return new Date(value.seconds * 1000).toISOString();
  return nowIso();
}

function mapRoom(id, data) {
  return {
    id,
    ...data,
    pricePerNight: Number(data.pricePerNight ?? data.price_per_night ?? 0),
    capacity: Number(data.capacity ?? 1),
    amenities: Array.isArray(data.amenities)
      ? data.amenities
      : String(data.amenities || '').split(',').map((item) => item.trim()).filter(Boolean),
    isActive: data.isActive !== false
  };
}

function mapBooking(id, data) {
  return {
    id,
    ...data,
    guests: Number(data.guests ?? 1),
    total: Number(data.total ?? 0),
    createdAt: timestampToIso(data.createdAt)
  };
}

function profileFromFirebaseUser(user, profile = {}) {
  return {
    uid: user.uid,
    email: user.email || '',
    fullName: profile.fullName || user.displayName || user.email?.split('@')[0] || 'Guest',
    role: profile.role || 'customer'
  };
}

function ensureLocalData() {
  const users = readLocal(LOCAL_KEYS.users, []);
  if (!users.some((user) => user.email === 'admin@paradise.local')) {
    users.push({
      uid: 'local-admin',
      email: 'admin@paradise.local',
      fullName: 'Administrator',
      password: 'admin123',
      role: 'admin'
    });
    writeLocal(LOCAL_KEYS.users, users);
  }
}

function localCurrentUser() {
  ensureLocalData();
  return readLocal(LOCAL_KEYS.user, null);
}

function dateOnly(value) {
  return String(value || '').slice(0, 10);
}

function localIsoDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export function enumerateNights(checkIn, checkOut) {
  const nights = [];
  const cursor = new Date(`${dateOnly(checkIn)}T00:00:00`);
  const end = new Date(`${dateOnly(checkOut)}T00:00:00`);
  while (cursor < end) {
    nights.push(localIsoDate(cursor));
    cursor.setDate(cursor.getDate() + 1);
  }
  return nights;
}

export function countNights(checkIn, checkOut) {
  return enumerateNights(checkIn, checkOut).length;
}

function rangesOverlap(aStart, aEnd, bStart, bEnd) {
  return dateOnly(aStart) < dateOnly(bEnd) && dateOnly(bStart) < dateOnly(aEnd);
}

function localBookings() {
  return readLocal(LOCAL_KEYS.bookings, []).map((booking) => mapBooking(booking.id, booking));
}

function localRoom(roomId) {
  return DEFAULT_ROOMS.find((room) => room.id === roomId) || null;
}

async function firebaseUserProfile(user) {
  const profileSnapshot = await getDoc(doc(firestore, 'users', user.uid));
  return profileFromFirebaseUser(user, profileSnapshot.exists() ? profileSnapshot.data() : {});
}

export function observeUser(callback) {
  if (!firebaseEnabled) {
    callback(localCurrentUser());
    return () => {};
  }
  return onAuthStateChanged(auth, async (user) => {
    callback(user ? await firebaseUserProfile(user) : null);
  });
}

export async function registerUser(fullName, email, password) {
  if (!firebaseEnabled) {
    ensureLocalData();
    const users = readLocal(LOCAL_KEYS.users, []);
    if (users.some((user) => user.email.toLowerCase() === email.toLowerCase())) {
      throw new Error('EMAIL_EXISTS');
    }
    const user = { uid: localId('user'), fullName, email, password, role: 'customer' };
    users.push(user);
    writeLocal(LOCAL_KEYS.users, users);
    const safeUser = { uid: user.uid, fullName, email, role: 'customer' };
    writeLocal(LOCAL_KEYS.user, safeUser);
    return safeUser;
  }

  const credentials = await createUserWithEmailAndPassword(auth, email, password);
  await updateProfile(credentials.user, { displayName: fullName });
  await setDoc(doc(firestore, 'users', credentials.user.uid), {
    fullName,
    email,
    role: 'customer',
    createdAt: serverTimestamp()
  });
  return profileFromFirebaseUser(credentials.user, { fullName, role: 'customer' });
}

export async function signIn(email, password) {
  if (!firebaseEnabled) {
    ensureLocalData();
    const user = readLocal(LOCAL_KEYS.users, []).find(
      (item) => item.email.toLowerCase() === email.toLowerCase() && item.password === password
    );
    if (!user) throw new Error('INVALID_LOGIN');
    const safeUser = { uid: user.uid, fullName: user.fullName, email: user.email, role: user.role };
    writeLocal(LOCAL_KEYS.user, safeUser);
    return safeUser;
  }

  const credentials = await signInWithEmailAndPassword(auth, email, password);
  return firebaseUserProfile(credentials.user);
}

export async function signOut() {
  if (firebaseEnabled) return firebaseSignOut(auth);
  localStorage.removeItem(LOCAL_KEYS.user);
}

export async function getRooms(force = false) {
  if (!force && roomsCache.rooms && Date.now() < roomsCache.expires) {
    return roomsCache.rooms;
  }
  if (!firebaseEnabled) {
    roomsCache = { expires: Date.now() + ROOMS_TTL_MS, rooms: DEFAULT_ROOMS };
    return DEFAULT_ROOMS;
  }
  const snapshot = await getDocs(query(collection(firestore, 'rooms'), where('isActive', '==', true)));
  const rooms = snapshot.empty ? DEFAULT_ROOMS : snapshot.docs.map((room) => mapRoom(room.id, room.data()));
  roomsCache = { expires: Date.now() + ROOMS_TTL_MS, rooms };
  return rooms;
}

export async function getRoomById(roomId) {
  const cached = roomsCache.rooms?.find((room) => room.id === roomId);
  if (cached) return cached;
  if (!firebaseEnabled) return localRoom(roomId);
  const snapshot = await getDoc(doc(firestore, 'rooms', roomId));
  if (snapshot.exists()) return mapRoom(snapshot.id, snapshot.data());
  return localRoom(roomId);
}

export async function getRoomAvailability(roomId) {
  if (!firebaseEnabled) {
    const bookings = localBookings().filter(
      (booking) => booking.roomId === roomId && ACTIVE_BOOKING_STATUSES.includes(booking.status)
    );
    return { bookings, blockedDates: [] };
  }

  const [lockSnapshot, blockedSnapshot] = await Promise.all([
    getDocs(query(collection(firestore, 'dateLocks'), where('roomId', '==', roomId))),
    getDocs(query(collection(firestore, 'blockedDates'), where('roomId', '==', roomId)))
  ]);
  const bookedDates = lockSnapshot.docs
    .map((item) => item.data())
    .filter((item) => item.status !== 'released')
    .map((item) => dateOnly(item.date))
    .filter(Boolean);
  const bookings = bookedDates.map((date) => ({
    id: `lock_${date}`,
    roomId,
    checkIn: date,
    checkOut: addDays(date, 1),
    status: 'approved'
  }));
  const blockedDates = blockedSnapshot.docs.map((item) => ({ id: item.id, ...item.data() }));
  return { bookings, blockedDates };
}

function addDays(value, amount) {
  const date = new Date(`${dateOnly(value)}T00:00:00`);
  date.setDate(date.getDate() + amount);
  return localIsoDate(date);
}

export async function createBooking({ user, room, checkIn, checkOut, guests }) {
  if (!user) throw new Error('LOGIN_REQUIRED');
  const nights = enumerateNights(checkIn, checkOut);
  if (!nights.length) throw new Error('INVALID_DATES');
  const total = nights.length * Number(room.pricePerNight);

  if (!firebaseEnabled) {
    const bookings = localBookings();
    const conflict = bookings.some(
      (booking) => booking.roomId === room.id && ACTIVE_BOOKING_STATUSES.includes(booking.status) &&
        rangesOverlap(checkIn, checkOut, booking.checkIn, booking.checkOut)
    );
    if (conflict) throw new Error('DATE_UNAVAILABLE');
    const booking = {
      id: localId('booking'),
      uid: user.uid,
      userId: user.uid,
      roomId: room.id,
      roomName: room.name,
      roomImagePath: room.imagePath,
      pricePerNight: Number(room.pricePerNight),
      checkIn,
      checkOut,
      guests: Number(guests),
      total,
      status: 'pending',
      createdAt: nowIso()
    };
    const stored = readLocal(LOCAL_KEYS.bookings, []);
    stored.push(booking);
    writeLocal(LOCAL_KEYS.bookings, stored);
    return mapBooking(booking.id, booking);
  }

  const bookingRef = doc(collection(firestore, 'bookings'));
  const lockRefs = nights.map((date) => doc(firestore, 'dateLocks', `${room.id}_${date}`));
  await runTransaction(firestore, async (transaction) => {
    const locks = [];
    for (const lockRef of lockRefs) locks.push(await transaction.get(lockRef));
    if (locks.some((lock) => lock.exists() && lock.data().status !== 'released')) {
      throw new Error('DATE_UNAVAILABLE');
    }
    transaction.set(bookingRef, {
      uid: user.uid,
      userId: user.uid,
      roomId: room.id,
      roomName: room.name,
      roomImagePath: room.imagePath,
      pricePerNight: Number(room.pricePerNight),
      checkIn,
      checkOut,
      guests: Number(guests),
      total,
      status: 'pending',
      createdAt: serverTimestamp()
    });
    nights.forEach((date, index) => {
      transaction.set(lockRefs[index], {
        roomId: room.id,
        date,
        bookingId: bookingRef.id,
        uid: user.uid,
        status: 'booked',
        createdAt: serverTimestamp()
      });
    });
  });
  return getBooking(bookingRef.id);
}

export async function getBooking(bookingId) {
  if (!firebaseEnabled) {
    const booking = localBookings().find((item) => item.id === bookingId);
    return booking ? { ...booking, room: localRoom(booking.roomId) } : null;
  }
  const snapshot = await getDoc(doc(firestore, 'bookings', bookingId));
  if (!snapshot.exists()) return null;
  const booking = mapBooking(snapshot.id, snapshot.data());
  return { ...booking, room: await getRoomById(booking.roomId) };
}

export async function getMyBookings(user) {
  if (!user) return [];
  if (!firebaseEnabled) {
    return localBookings()
      .filter((booking) => booking.uid === user.uid)
      .map((booking) => ({ ...booking, room: localRoom(booking.roomId) }))
      .sort((a, b) => b.createdAt.localeCompare(a.createdAt));
  }
  const snapshot = await getDocs(query(collection(firestore, 'bookings'), where('uid', '==', user.uid)));
  const rooms = await getRooms();
  return snapshot.docs
    .map((item) => mapBooking(item.id, item.data()))
    .map((booking) => ({ ...booking, room: rooms.find((room) => room.id === booking.roomId) || null }))
    .sort((a, b) => b.createdAt.localeCompare(a.createdAt));
}

async function releaseLocks(transaction, booking) {
  const lockRefs = enumerateNights(booking.checkIn, booking.checkOut)
    .map((date) => doc(firestore, 'dateLocks', `${booking.roomId}_${date}`));
  for (const lockRef of lockRefs) {
    const lock = await transaction.get(lockRef);
    if (lock.exists() && lock.data().bookingId === booking.id) transaction.delete(lockRef);
  }
}

export async function cancelBooking(bookingId, user) {
  if (!firebaseEnabled) {
    const bookings = readLocal(LOCAL_KEYS.bookings, []);
    const booking = bookings.find((item) => item.id === bookingId && item.uid === user.uid);
    if (!booking) throw new Error('BOOKING_NOT_FOUND');
    if (booking.status !== 'pending') throw new Error('CANNOT_CANCEL');
    booking.status = 'cancelled';
    writeLocal(LOCAL_KEYS.bookings, bookings);
    return mapBooking(booking.id, booking);
  }

  const bookingRef = doc(firestore, 'bookings', bookingId);
  await runTransaction(firestore, async (transaction) => {
    const snapshot = await transaction.get(bookingRef);
    if (!snapshot.exists() || snapshot.data().uid !== user.uid) throw new Error('BOOKING_NOT_FOUND');
    const booking = mapBooking(snapshot.id, snapshot.data());
    if (booking.status !== 'pending') throw new Error('CANNOT_CANCEL');
    await releaseLocks(transaction, booking);
    transaction.update(bookingRef, { status: 'cancelled', cancelledAt: serverTimestamp() });
  });
  return getBooking(bookingId);
}

export async function sendContactMessage(user, name, email, message) {
  if (!name || !email || !message) throw new Error('MISSING_MESSAGE_FIELDS');
  if (!firebaseEnabled) {
    const messages = readLocal(LOCAL_KEYS.messages, []);
    messages.push({ id: localId('message'), uid: user?.uid || null, name, email, message, createdAt: nowIso() });
    writeLocal(LOCAL_KEYS.messages, messages);
    return;
  }
  await addDoc(collection(firestore, 'contactMessages'), {
    uid: user?.uid || null,
    name,
    email,
    message,
    createdAt: serverTimestamp()
  });
}

export async function getAllBookings(user) {
  if (!user || user.role !== 'admin') throw new Error('ADMIN_REQUIRED');
  if (!firebaseEnabled) {
    return localBookings()
      .map((booking) => ({ ...booking, room: localRoom(booking.roomId) }))
      .sort((a, b) => b.createdAt.localeCompare(a.createdAt));
  }
  const snapshot = await getDocs(collection(firestore, 'bookings'));
  const rooms = await getRooms();
  return snapshot.docs
    .map((item) => mapBooking(item.id, item.data()))
    .map((booking) => ({ ...booking, room: rooms.find((room) => room.id === booking.roomId) || null }))
    .sort((a, b) => b.createdAt.localeCompare(a.createdAt));
}

export async function updateBookingStatus(bookingId, status, user) {
  if (!user || user.role !== 'admin') throw new Error('ADMIN_REQUIRED');
  if (!['approved', 'rejected', 'cancelled', 'checked_in', 'completed', 'no_show'].includes(status)) {
    throw new Error('INVALID_STATUS');
  }
  if (!firebaseEnabled) {
    const bookings = readLocal(LOCAL_KEYS.bookings, []);
    const booking = bookings.find((item) => item.id === bookingId);
    if (!booking) throw new Error('BOOKING_NOT_FOUND');
    booking.status = status;
    writeLocal(LOCAL_KEYS.bookings, bookings);
    return;
  }

  const bookingRef = doc(firestore, 'bookings', bookingId);
  await runTransaction(firestore, async (transaction) => {
    const snapshot = await transaction.get(bookingRef);
    if (!snapshot.exists()) throw new Error('BOOKING_NOT_FOUND');
    const booking = mapBooking(snapshot.id, snapshot.data());
    if (status === 'rejected' || status === 'cancelled') await releaseLocks(transaction, booking);
    transaction.update(bookingRef, { status, updatedAt: serverTimestamp() });
  });
}

export function firebaseModeLabel() {
  return firebaseEnabled ? 'Firebase / Firestore' : 'Local demo mode';
}
