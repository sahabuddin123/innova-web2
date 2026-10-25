/**
 * INNOVA WEB — Centralized API Endpoints Map
 *
 * Defines canonical paths for backend REST API v1 endpoints.
 */
export const API_ENDPOINTS = {
  // Foundation
  ping: '/ping',

  // Authentication
  auth: {
    adminLogin: '/admin/auth/login',
    adminLogout: '/admin/auth/logout',
    adminMe: '/admin/auth/me',
    userLogin: '/auth/login',
    userLogout: '/auth/logout',
    userMe: '/auth/me',
  },

  // Academic & Courses
  academic: {
    categories: '/academic/categories',
    courses: '/academic/courses',
    sessions: '/academic/sessions',
    batches: '/academic/batches',
    teachers: '/academic/teachers',
    students: '/academic/students',
    admissions: '/academic/admissions',
  },

  // System Settings
  settings: {
    public: '/settings/public',
    index: '/settings',
    sms: '/settings/sms',
  },

  // SMS Gateway
  sms: {
    send: '/sms/send',
    sendTemplate: '/sms/send-template',
    sendBatch: '/sms/send-batch',
    logs: '/sms/logs',
    templates: '/sms/templates',
    testConnection: '/sms/test-connection',
  },

  // Certificates & Verification
  certificates: {
    verify: (certNo: string) => `/certificates/verify/${certNo}`,
    summary: '/certificates/reports/summary',
    eligibility: (studentId: number | string) => `/certificates/eligibility/${studentId}`,
    bteb: '/certificates/bteb',
    icbd: '/certificates',
  },
} as const;
