import './bootstrap';
import React from 'react';
import ReactDOM from 'react-dom/client';
import HelloWorld from './components/HelloWorld';

const el = document.getElementById('app');
if (el) {
  ReactDOM.createRoot(el).render(
    <React.StrictMode>
      <HelloWorld />
    </React.StrictMode>
  );
}
