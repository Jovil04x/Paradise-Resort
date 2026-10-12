// Firebase web configuration is safe to expose in a browser. Keep private service-account
// credentials out of this file. Copy the values from Firebase Console > Project settings.
const configured = globalThis.PARADISE_FIREBASE_CONFIG || {
  apiKey: '',
  authDomain: '',
  projectId: '',
  storageBucket: '',
  messagingSenderId: '',
  appId: ''
};

export const firebaseConfig = configured;
export const hasFirebaseConfig = Boolean(
  configured.apiKey &&
  configured.authDomain &&
  configured.projectId &&
  configured.appId
);
