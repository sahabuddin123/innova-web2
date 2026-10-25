import React, { useState, useEffect } from 'react';
import { BrowserRouter } from 'react-router-dom';
import { get, API_ENDPOINTS } from '../api';
import { ApiPingData } from '../types/api';
import { getApiBaseUrl } from '../utils/env';
import { Card, Alert, LoadingSpinner } from '../components/common';
import { AuthProvider } from '../context/AuthContext';
import { ThemeProvider } from '../context/ThemeContext';
import { AuthDemo } from '../components/auth/AuthDemo';
import { AdminRoutes } from '../routes/AdminRoutes';

/**
 * Diagnostics and Health Verification Content (/react-health)
 */
const HealthVerificationContent: React.FC = () => {
  const [pingData, setPingData] = useState<ApiPingData | null>(null);
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const apiBaseUrl = getApiBaseUrl();

  const handleTestPing = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await get<ApiPingData>(API_ENDPOINTS.ping);
      setPingData(response.data);
    } catch (err) {
      const e = err as { message?: string };
      setError(e.message || 'Failed to connect to API endpoint.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    handleTestPing();
  }, []);

  return (
    <div style={{ maxWidth: '960px', margin: '2rem auto', padding: '0 1rem', display: 'flex', flexDirection: 'column', gap: '2rem' }}>
      {/* Shell Launch Banner */}
      <Card
        title="React Admin Application Shell (Phase 3.2)"
        subtitle="Dedicated SPA Shell mounted at /react-admin"
        footer={
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <span style={{ fontSize: '0.85rem', color: 'var(--innova-gray-500)' }}>
              Target Namespace: <code>/react-admin/*</code>
            </span>
            <a href="/react-admin/dashboard" className="innova-btn innova-btn-primary">
              Launch React Admin Shell &rarr;
            </a>
          </div>
        }
      >
        <p style={{ margin: 0, fontSize: '0.9rem', color: 'var(--innova-gray-700)' }}>
          The React Admin Application Shell provides responsive navigation, theme toggling, Spatie role-aware item filtering, breadcrumbs, and client-side routing on top of Laravel Sanctum authentication.
        </p>
      </Card>

      {/* Phase 3.1: React Authentication & Access Foundation Demonstration Mount */}
      <AuthDemo />

      {/* Phase 3.0: Dual-Engine Architecture & API Ping Verification Mount */}
      <Card
        title="INNOVA Computers BD — React Frontend Foundation"
        subtitle="Phase 3.0: Setup Only — Dual-Engine Architecture Verification"
        footer={
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <span style={{ fontSize: '0.85rem', color: 'var(--innova-gray-500)' }}>
              Resolved API Base: <code>{apiBaseUrl}</code>
            </span>
            <button
              type="button"
              className="innova-btn innova-btn-primary"
              onClick={handleTestPing}
              disabled={loading}
            >
              {loading ? <LoadingSpinner size="sm" label="Testing API..." /> : 'Test API v1 Ping'}
            </button>
          </div>
        }
      >
        <Alert type="success" title="React 18 & TypeScript Foundation Active">
          The modern frontend foundation has been established using React 18, TypeScript, and Vite.
          This setup runs concurrently alongside the legacy Laravel Mix pipeline with zero regression to existing Blade pages.
        </Alert>

        {error && (
          <Alert type="error" title="API Connectivity Notice">
            {error}
          </Alert>
        )}

        <div className="innova-grid" style={{ marginTop: '1.5rem' }}>
          <div className="innova-stat-box">
            <div className="innova-stat-label">Frontend Framework</div>
            <div className="innova-stat-value">React 18.2.0</div>
            <span className="innova-badge innova-badge-success" style={{ marginTop: '0.5rem' }}>
              Operational
            </span>
          </div>

          <div className="innova-stat-box">
            <div className="innova-stat-label">Type System</div>
            <div className="innova-stat-value">TypeScript 5/7</div>
            <span className="innova-badge innova-badge-primary" style={{ marginTop: '0.5rem' }}>
              Type-Safe Envelopes
            </span>
          </div>

          <div className="innova-stat-box">
            <div className="innova-stat-label">Build Tool</div>
            <div className="innova-stat-value">Vite Bundler</div>
            <span className="innova-badge innova-badge-success" style={{ marginTop: '0.5rem' }}>
              ESModules & HMR
            </span>
          </div>

          <div className="innova-stat-box">
            <div className="innova-stat-label">API v1 Status</div>
            <div className="innova-stat-value">
              {loading ? (
                <LoadingSpinner size="sm" />
              ) : pingData ? (
                `v${pingData.api_version}`
              ) : (
                'Pending Check'
              )}
            </div>
            {pingData && (
              <span style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', display: 'block', marginTop: '0.25rem' }}>
                Env: {pingData.environment} | {new Date(pingData.timestamp).toLocaleTimeString()}
              </span>
            )}
          </div>
        </div>

        <div style={{ marginTop: '1.5rem', padding: '1rem', background: '#f8fafc', borderRadius: '8px', border: '1px solid #e2e8f0' }}>
          <h4 style={{ margin: '0 0 0.5rem 0', fontSize: '0.95rem', fontWeight: 600 }}>Architecture Compliance Guarantee:</h4>
          <ul style={{ margin: 0, paddingLeft: '1.25rem', fontSize: '0.85rem', color: '#475569' }}>
            <li>No Blade templates or existing business modules have been altered or replaced.</li>
            <li>Laravel Sanctum authentication and multi-guard session systems remain intact.</li>
            <li>Existing Laravel Mix assets (<code>public/js/app.js</code>, <code>public/css/app.css</code>) remain active for all production pages.</li>
            <li>React assets are compiled independently into <code>public/react/main.js</code> and <code>public/react/main.css</code>.</li>
          </ul>
        </div>
      </Card>
    </div>
  );
};

/**
 * Root Application component.
 * Automatically resolves between dedicated /react-admin routing and /react-health diagnostics.
 */
export const App: React.FC = () => {
  const isDedicatedAdmin = typeof window !== 'undefined' && (
    window.location.pathname.startsWith('/react-admin') ||
    document.getElementById('react-root')?.getAttribute('data-app-mode') === 'admin'
  );

  return (
    <ThemeProvider>
      <AuthProvider>
        {isDedicatedAdmin ? (
          <BrowserRouter basename="/react-admin">
            <AdminRoutes />
          </BrowserRouter>
        ) : (
          <HealthVerificationContent />
        )}
      </AuthProvider>
    </ThemeProvider>
  );
};
