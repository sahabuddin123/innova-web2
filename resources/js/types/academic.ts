/**
 * INNOVA WEB — Academic, Course & Batch TypeScript Contracts
 *
 * Models and request interfaces matching Laravel Eloquent models and ApiController envelopes.
 */

export interface CourseCategory {
  id: number;
  CourseCategoris: string;
  isFeature?: number;
  IsActive?: number;
  created_at?: string;
  updated_at?: string;
}

export interface Teacher {
  id: number;
  name: string;
  phone?: string | null;
  email?: string | null;
  designation?: string | null;
  isTeacher?: number;
  IsActive?: number;
}

export interface Course {
  id: number;
  name: string;
  coursefee: string;
  category_id?: number | null;
  shortTitle?: string | null;
  courseduration?: string | null;
  lacture?: string | null;
  totalmoduls?: string | null;
  totalproject?: string | null;
  description?: string | null;
  overview?: string | null;
  curriculum?: string | null;
  software?: string | null;
  trainee?: string | null;
  jobPlacement?: string | null;
  jobPosition?: string | null;
  IsActive: number;
  featured?: number;
  IsCourse?: number;
  image?: string | null;
  video?: string | null;
  Instructor?: string | null;
  Rating?: string | null;
  TotalReview?: string | null;
  Discount?: string | number | null;
  views?: number;
  created_at?: string;
  updated_at?: string;
  CourseCategory?: CourseCategory | null;
  course_category?: CourseCategory | null;
}

export interface CourseFormData {
  name: string;
  coursefee: string;
  category_id?: number | null;
  shortTitle?: string;
  courseduration?: string;
  lacture?: string;
  totalmoduls?: string;
  totalproject?: string;
  description?: string;
  overview?: string;
  curriculum?: string;
  software?: string;
  trainee?: string;
  jobPlacement?: string;
  jobPosition?: string;
  IsActive?: number;
  featured?: number;
  IsCourse?: number;
  image?: string;
  video?: string;
  Instructor?: string;
  Discount?: number | string;
}

export interface CourseFilters {
  category_id?: number;
  is_active?: number;
  featured?: number;
  search?: string;
  per_page?: number;
  page?: number;
}

export interface Batch {
  id: number;
  stuff_id?: number | null;
  batchnum: string;
  totalseat: string;
  startdate?: string | null;
  enddate?: string | null;
  day1?: number;
  schedule1?: string | null;
  day2?: number;
  schedule2?: string | null;
  day3?: number;
  schedule3?: string | null;
  day4?: number;
  schedule4?: string | null;
  day5?: number;
  schedule5?: string | null;
  day6?: number;
  schedule6?: string | null;
  day7?: number;
  schedule7?: string | null;
  IsActive: number; // 0 = Postponed, 1 = Running, 2 = Upcoming, 3 = Completed
  created_at?: string;
  updated_at?: string;
  teacher?: Teacher | null;
}

export interface BatchFormData {
  batchnum: string;
  totalseat: string;
  stuff_id?: number | null;
  startdate?: string | null;
  enddate?: string | null;
  day1?: number;
  schedule1?: string | null;
  day2?: number;
  schedule2?: string | null;
  day3?: number;
  schedule3?: string | null;
  day4?: number;
  schedule4?: string | null;
  day5?: number;
  schedule5?: string | null;
  day6?: number;
  schedule6?: string | null;
  day7?: number;
  schedule7?: string | null;
  IsActive?: number;
}

export interface BatchFilters {
  status?: 'all' | 'running' | 'upcoming' | 'completed' | 'postponed';
  is_active?: number;
  search?: string;
  per_page?: number;
  page?: number;
}

export type BatchStatusType = 0 | 1 | 2 | 3;

export const BATCH_STATUS_MAP: Record<number, { label: string; badge: string; color: string }> = {
  0: { label: 'Postponed', badge: 'innova-badge-error', color: '#ef4444' },
  1: { label: 'Running', badge: 'innova-badge-success', color: '#10b981' },
  2: { label: 'Upcoming', badge: 'innova-badge-primary', color: '#4f46e5' },
  3: { label: 'Completed', badge: 'innova-badge-gray', color: '#6b7280' },
};

export const WEEK_DAYS = [
  { key: 'day1', scheduleKey: 'schedule1', label: 'Friday' },
  { key: 'day2', scheduleKey: 'schedule2', label: 'Saturday' },
  { key: 'day3', scheduleKey: 'schedule3', label: 'Sunday' },
  { key: 'day4', scheduleKey: 'schedule4', label: 'Monday' },
  { key: 'day5', scheduleKey: 'schedule5', label: 'Tuesday' },
  { key: 'day6', scheduleKey: 'schedule6', label: 'Wednesday' },
  { key: 'day7', scheduleKey: 'schedule7', label: 'Thursday' },
] as const;
