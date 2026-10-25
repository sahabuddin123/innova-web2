import React from 'react';
import ReactDOM from 'react-dom/client';
import { App } from './App';
import './styles/index.css';

/**
 * INNOVA WEB — React Root Mount Entry Point (Phase 3.0)
 *
 * Mounts the React application tree onto `#react-root` using React 18 createRoot API.
 */
function mountReactApplication() {
  const container = document.getElementById('react-root');

  if (!container) {
    // If react-root container is not present on current page, safely do nothing (Blade coexistence)
    return;
  }

  const root = ReactDOM.createRoot(container);

  root.render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  );
}

// Execute on DOMContentLoaded or immediately if document is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mountReactApplication);
} else {
  mountReactApplication();
}
