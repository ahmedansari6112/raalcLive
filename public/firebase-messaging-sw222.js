
// Import the Firebase libraries for Firebase app and messaging
importScripts('https://www.gstatic.com/firebasejs/10.13.0/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/10.13.0/firebase-messaging.js');

// Your Firebase configuration
const firebaseConfig = {
    apiKey: "AIzaSyDrG6Lz1qlZZi33ey7uN2OwxbT7KmT5myk",
    authDomain: "raalc-notification.firebaseapp.com",
    projectId: "raalc-notification",
    storageBucket: "raalc-notification.appspot.com",
    messagingSenderId: "143422953914",
    appId: "1:143422953914:web:8ddcd32ba2877a2977e583",
    measurementId: "G-7TSR8514S2"
};

// Initialize the Firebase app in the service worker
firebase.initializeApp(firebaseConfig);

// Retrieve an instance of Firebase Messaging to handle background messages
const messaging = firebase.messaging();

// Handle background messages
messaging.onBackgroundMessage((payload) => {
    console.log('Received background message: ', payload);

    // Customize notification here
    const notificationTitle = payload.notification.title;
    const notificationOptions = {
        body: payload.notification.body,
        icon: '/firebase-logo.png'
    };

    // Show notification
    self.registration.showNotification(notificationTitle, notificationOptions);
});
