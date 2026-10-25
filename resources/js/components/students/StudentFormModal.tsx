import React, { useState, useEffect } from 'react';
import {
  Student,
  StudentFormData,
  Course,
  Batch,
  GENDER_OPTIONS,
  RELIGION_OPTIONS,
  BLOOD_GROUP_OPTIONS,
  GUARDIAN_RELATION_OPTIONS,
  STUDENT_STATUS_MAP,
} from '../../types';
import { studentService, courseService, batchService } from '../../services';
import { Alert } from '../common';

interface StudentFormModalProps {
  student: Student | null; // null = create mode; Student = edit mode
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (savedStudent: Student) => void;
}

type FormTab = 'basic' | 'academic' | 'financial' | 'guardian';

export const StudentFormModal: React.FC<StudentFormModalProps> = ({
  student,
  isOpen,
  onClose,
  onSuccess,
}) => {
  if (!isOpen) return null;

  const isEditMode = Boolean(student);

  // Form tabs
  const [activeTab, setActiveTab] = useState<FormTab>('basic');

  // Academic dropdown catalogs
  const [courses, setCourses] = useState<Course[]>([]);
  const [batches, setBatches] = useState<Batch[]>([]);
  const [isLoadingLookups, setIsLoadingLookups] = useState<boolean>(true);

  // Form State
  const [formData, setFormData] = useState<StudentFormData>({
    sname: '',
    gender: 'Male',
    religion: 'Islam',
    phone: '',
    course_id: '',
    batch_id: '',
    branch_id: '',
    course_fee: '0',
    discount: '0',
    balance: '0',
    admission_date: new Date().toISOString().split('T')[0],
    g_name: '',
    g_phone: '',
    rel_g: 'Father',
    dob: '',
    bloodgroup: '',
    height: '',
    weight: '',
    nid: '',
    last_school: '',
    f_name: '',
    f_phone: '',
    m_name: '',
    m_phone: '',
    ref: '',
    image: '',
    IsRegister: 1,
    IsActive: 1,
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);

  // Load courses and batches on mount
  useEffect(() => {
    let isMounted = true;
    const loadCatalogs = async () => {
      setIsLoadingLookups(true);
      try {
        const [courseRes, batchRes] = await Promise.all([
          courseService.getCourses({ per_page: 100 }),
          batchService.getBatches({ per_page: 100 }),
        ]);
        if (isMounted) {
          setCourses(courseRes.data || []);
          setBatches(batchRes.data || []);
        }
      } catch {
        // Catalogs failed gracefully
      } finally {
        if (isMounted) setIsLoadingLookups(false);
      }
    };

    loadCatalogs();
    return () => {
      isMounted = false;
    };
  }, []);

  // Initialize form data when student prop changes
  useEffect(() => {
    if (student) {
      setFormData({
        sname: student.sname || '',
        gender: student.gender || 'Male',
        religion: student.religion || 'Islam',
        phone: student.phone || '',
        course_id: student.course_id || '',
        batch_id: student.batch_id || '',
        branch_id: student.branch_id || '',
        course_fee: String(student.course_fee || '0'),
        discount: String(student.discount || '0'),
        balance: String(student.balance || '0'),
        admission_date: student.admission_date
          ? student.admission_date.split('T')[0]
          : new Date().toISOString().split('T')[0],
        g_name: student.g_name || '',
        g_phone: student.g_phone || '',
        rel_g: student.rel_g || 'Father',
        dob: student.dob ? student.dob.split('T')[0] : '',
        bloodgroup: student.bloodgroup || '',
        height: student.height || '',
        weight: student.weight || '',
        nid: student.nid || '',
        last_school: student.last_school || '',
        f_name: student.f_name || '',
        f_phone: student.f_phone || '',
        m_name: student.m_name || '',
        m_phone: student.m_phone || '',
        ref: student.ref || '',
        image: student.image || '',
        IsRegister: student.IsRegister !== undefined ? student.IsRegister : 1,
        IsActive: student.IsActive !== undefined ? student.IsActive : 1,
      });
    } else {
      setFormData({
        sname: '',
        gender: 'Male',
        religion: 'Islam',
        phone: '',
        course_id: '',
        batch_id: '',
        branch_id: '',
        course_fee: '0',
        discount: '0',
        balance: '0',
        admission_date: new Date().toISOString().split('T')[0],
        g_name: '',
        g_phone: '',
        rel_g: 'Father',
        dob: '',
        bloodgroup: '',
        height: '',
        weight: '',
        nid: '',
        last_school: '',
        f_name: '',
        f_phone: '',
        m_name: '',
        m_phone: '',
        ref: '',
        image: '',
        IsRegister: 1,
        IsActive: 1,
      });
    }
    setErrors({});
    setGeneralError(null);
    setActiveTab('basic');
  }, [student, isOpen]);

  // Handle course selection and auto-load fee
  const handleCourseChange = (courseId: number | '') => {
    setFormData((prev) => {
      const selected = courses.find((c) => c.id === courseId);
      const newFee = selected && selected.coursefee ? String(selected.coursefee) : prev.course_fee;
      const numFee = Number(newFee) || 0;
      const numDiscount = Number(prev.discount) || 0;
      const newBalance = Math.max(0, numFee - numDiscount);

      return {
        ...prev,
        course_id: courseId,
        course_fee: newFee,
        balance: String(newBalance),
      };
    });
  };

  // Recalculate balance when fee or discount changes
  const handleFeeOrDiscountChange = (field: 'course_fee' | 'discount', value: string) => {
    setFormData((prev) => {
      const updated = { ...prev, [field]: value };
      const numFee = Number(field === 'course_fee' ? value : updated.course_fee) || 0;
      const numDiscount = Number(field === 'discount' ? value : updated.discount) || 0;
      const newBalance = Math.max(0, numFee - numDiscount);
      return {
        ...updated,
        balance: String(newBalance),
      };
    });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});
    setGeneralError(null);
    setIsSubmitting(true);

    try {
      let saved: Student;
      if (isEditMode && student) {
        saved = await studentService.updateStudent(student.id, formData);
      } else {
        saved = await studentService.createStudent(formData);
      }
      onSuccess(saved);
      onClose();
    } catch (err: unknown) {
      const apiErr = err as {
        response?: {
          status?: number;
          data?: {
            message?: string;
            errors?: Record<string, string[]>;
          };
        };
        message?: string;
      };

      if (apiErr?.response?.status === 422 && apiErr.response.data?.errors) {
        const rawErrors = apiErr.response.data.errors;
        const mappedErrors: Record<string, string> = {};
        for (const key of Object.keys(rawErrors)) {
          mappedErrors[key] = rawErrors[key][0];
        }
        setErrors(mappedErrors);
        setGeneralError(apiErr.response.data.message || 'Please review validation errors below.');
      } else {
        setGeneralError(apiErr?.response?.data?.message || apiErr?.message || 'Failed to save student.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="innova-modal-backdrop" onClick={onClose} role="dialog" aria-modal="true">
      <div
        className="innova-modal innova-modal-lg"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: '820px' }}
      >
        {/* Header */}
        <div className="innova-modal-header">
          <div>
            <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
              Student Management
            </div>
            <h2 className="innova-modal-title">
              {isEditMode ? `Edit Student — ${student?.sname}` : 'Enroll New Student'}
            </h2>
          </div>
          <button
            type="button"
            className="innova-btn innova-btn-sm innova-btn-secondary"
            onClick={onClose}
            aria-label="Close"
          >
            &times;
          </button>
        </div>

        {/* Tab Navigation */}
        <div style={{ padding: '0.75rem 1.5rem 0', backgroundColor: 'var(--innova-card-bg)' }}>
          <div className="innova-tabs" style={{ marginBottom: 0 }}>
            <button
              type="button"
              className={`innova-tab ${activeTab === 'basic' ? 'innova-tab-active' : ''}`}
              onClick={() => setActiveTab('basic')}
            >
              1. Basic Info
            </button>
            <button
              type="button"
              className={`innova-tab ${activeTab === 'academic' ? 'innova-tab-active' : ''}`}
              onClick={() => setActiveTab('academic')}
            >
              2. Academic & Batch
            </button>
            <button
              type="button"
              className={`innova-tab ${activeTab === 'financial' ? 'innova-tab-active' : ''}`}
              onClick={() => setActiveTab('financial')}
            >
              3. Financials & Fee
            </button>
            <button
              type="button"
              className={`innova-tab ${activeTab === 'guardian' ? 'innova-tab-active' : ''}`}
              onClick={() => setActiveTab('guardian')}
            >
              4. Guardian & Family
            </button>
          </div>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', flex: 1, overflow: 'hidden' }}>
          <div className="innova-modal-body" style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
            {generalError && (
              <Alert type="error" title="Submission Error">
                {generalError}
              </Alert>
            )}

            {/* TAB 1: Basic Information */}
            {activeTab === 'basic' && (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.85rem' }}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '0.85rem' }}>
                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-sname">
                      Student Full Name <span style={{ color: 'var(--innova-error)' }}>*</span>
                    </label>
                    <input
                      id="student-sname"
                      type="text"
                      className="innova-form-control"
                      value={formData.sname}
                      onChange={(e) => setFormData({ ...formData, sname: e.target.value })}
                      placeholder="e.g. Mohammad Ali"
                      required
                    />
                    {errors.sname && <div className="innova-form-error">{errors.sname}</div>}
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-phone">
                      Mobile Phone Number
                    </label>
                    <input
                      id="student-phone"
                      type="text"
                      className="innova-form-control"
                      value={formData.phone || ''}
                      onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                      placeholder="e.g. 01712345678"
                    />
                    {errors.phone && <div className="innova-form-error">{errors.phone}</div>}
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '0.85rem' }}>
                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-gender">
                      Gender <span style={{ color: 'var(--innova-error)' }}>*</span>
                    </label>
                    <select
                      id="student-gender"
                      className="innova-form-control"
                      value={formData.gender}
                      onChange={(e) => setFormData({ ...formData, gender: e.target.value })}
                      required
                    >
                      {GENDER_OPTIONS.map((g) => (
                        <option key={g} value={g}>
                          {g}
                        </option>
                      ))}
                    </select>
                    {errors.gender && <div className="innova-form-error">{errors.gender}</div>}
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-religion">
                      Religion <span style={{ color: 'var(--innova-error)' }}>*</span>
                    </label>
                    <select
                      id="student-religion"
                      className="innova-form-control"
                      value={formData.religion}
                      onChange={(e) => setFormData({ ...formData, religion: e.target.value })}
                      required
                    >
                      {RELIGION_OPTIONS.map((r) => (
                        <option key={r} value={r}>
                          {r}
                        </option>
                      ))}
                    </select>
                    {errors.religion && <div className="innova-form-error">{errors.religion}</div>}
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-bloodgroup">
                      Blood Group
                    </label>
                    <select
                      id="student-bloodgroup"
                      className="innova-form-control"
                      value={formData.bloodgroup || ''}
                      onChange={(e) => setFormData({ ...formData, bloodgroup: e.target.value })}
                    >
                      <option value="">Select Blood Group</option>
                      {BLOOD_GROUP_OPTIONS.map((b) => (
                        <option key={b} value={b}>
                          {b}
                        </option>
                      ))}
                    </select>
                    {errors.bloodgroup && <div className="innova-form-error">{errors.bloodgroup}</div>}
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-dob">
                      Date of Birth
                    </label>
                    <input
                      id="student-dob"
                      type="date"
                      className="innova-form-control"
                      value={formData.dob || ''}
                      onChange={(e) => setFormData({ ...formData, dob: e.target.value })}
                    />
                    {errors.dob && <div className="innova-form-error">{errors.dob}</div>}
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '0.85rem' }}>
                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-nid">
                      National ID (NID) / Birth Certificate
                    </label>
                    <input
                      id="student-nid"
                      type="text"
                      className="innova-form-control"
                      value={formData.nid || ''}
                      onChange={(e) => setFormData({ ...formData, nid: e.target.value })}
                      placeholder="e.g. 19921234567890"
                    />
                    {errors.nid && <div className="innova-form-error">{errors.nid}</div>}
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-last-school">
                      Previous School / College
                    </label>
                    <input
                      id="student-last-school"
                      type="text"
                      className="innova-form-control"
                      value={formData.last_school || ''}
                      onChange={(e) => setFormData({ ...formData, last_school: e.target.value })}
                      placeholder="e.g. Dhaka City College"
                    />
                    {errors.last_school && <div className="innova-form-error">{errors.last_school}</div>}
                  </div>
                </div>
              </div>
            )}

            {/* TAB 2: Academic & Batch */}
            {activeTab === 'academic' && (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.85rem' }}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '0.85rem' }}>
                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-course">
                      Course <span style={{ color: 'var(--innova-error)' }}>*</span>
                    </label>
                    <select
                      id="student-course"
                      className="innova-form-control"
                      value={formData.course_id}
                      onChange={(e) => handleCourseChange(e.target.value ? Number(e.target.value) : '')}
                      required
                    >
                      <option value="">{isLoadingLookups ? 'Loading courses...' : 'Select Course'}</option>
                      {courses.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.name} {c.shortTitle ? `(${c.shortTitle})` : ''} — {c.coursefee} BDT
                        </option>
                      ))}
                    </select>
                    {errors.course_id && <div className="innova-form-error">{errors.course_id}</div>}
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-batch">
                      Batch <span style={{ color: 'var(--innova-error)' }}>*</span>
                    </label>
                    <select
                      id="student-batch"
                      className="innova-form-control"
                      value={formData.batch_id}
                      onChange={(e) => setFormData({ ...formData, batch_id: e.target.value ? Number(e.target.value) : '' })}
                      required
                    >
                      <option value="">{isLoadingLookups ? 'Loading batches...' : 'Select Batch'}</option>
                      {batches.map((b) => (
                        <option key={b.id} value={b.id}>
                          {b.batchnum} ({b.totalseat} Seats)
                        </option>
                      ))}
                    </select>
                    {errors.batch_id && <div className="innova-form-error">{errors.batch_id}</div>}
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '0.85rem' }}>
                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-admission-date">
                      Admission Date <span style={{ color: 'var(--innova-error)' }}>*</span>
                    </label>
                    <input
                      id="student-admission-date"
                      type="date"
                      className="innova-form-control"
                      value={formData.admission_date}
                      onChange={(e) => setFormData({ ...formData, admission_date: e.target.value })}
                      required
                    />
                    {errors.admission_date && <div className="innova-form-error">{errors.admission_date}</div>}
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-status">
                      Enrollment Status
                    </label>
                    <select
                      id="student-status"
                      className="innova-form-control"
                      value={formData.IsActive}
                      onChange={(e) => setFormData({ ...formData, IsActive: Number(e.target.value) })}
                    >
                      {Object.values(STUDENT_STATUS_MAP).map((meta) => (
                        <option key={meta.code} value={meta.code}>
                          {meta.label} ({meta.description})
                        </option>
                      ))}
                    </select>
                    {errors.IsActive && <div className="innova-form-error">{errors.IsActive}</div>}
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-register">
                      Registration Status
                    </label>
                    <select
                      id="student-register"
                      className="innova-form-control"
                      value={formData.IsRegister}
                      onChange={(e) => setFormData({ ...formData, IsRegister: Number(e.target.value) })}
                    >
                      <option value={1}>Registered for Certificate / Board</option>
                      <option value={0}>Not Registered</option>
                    </select>
                    {errors.IsRegister && <div className="innova-form-error">{errors.IsRegister}</div>}
                  </div>
                </div>
              </div>
            )}

            {/* TAB 3: Financials & Fee */}
            {activeTab === 'financial' && (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.85rem' }}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '0.85rem' }}>
                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-fee">
                      Course Tuition Fee (BDT) <span style={{ color: 'var(--innova-error)' }}>*</span>
                    </label>
                    <input
                      id="student-fee"
                      type="number"
                      min="0"
                      className="innova-form-control"
                      value={formData.course_fee}
                      onChange={(e) => handleFeeOrDiscountChange('course_fee', e.target.value)}
                      required
                    />
                    {errors.course_fee && <div className="innova-form-error">{errors.course_fee}</div>}
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-discount">
                      Discount Granted (BDT)
                    </label>
                    <input
                      id="student-discount"
                      type="number"
                      min="0"
                      className="innova-form-control"
                      value={formData.discount || '0'}
                      onChange={(e) => handleFeeOrDiscountChange('discount', e.target.value)}
                    />
                    {errors.discount && <div className="innova-form-error">{errors.discount}</div>}
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-balance">
                      Calculated Net Payable (BDT)
                    </label>
                    <input
                      id="student-balance"
                      type="text"
                      className="innova-form-control"
                      value={formData.balance || '0'}
                      readOnly
                      style={{ backgroundColor: 'var(--innova-gray-100)', fontWeight: 600 }}
                    />
                    <div className="innova-form-hint">Automatically calculated: Tuition Fee − Discount</div>
                  </div>
                </div>
              </div>
            )}

            {/* TAB 4: Guardian & Family */}
            {activeTab === 'guardian' && (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.85rem' }}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '0.85rem' }}>
                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-gname">
                      Primary Guardian Name <span style={{ color: 'var(--innova-error)' }}>*</span>
                    </label>
                    <input
                      id="student-gname"
                      type="text"
                      className="innova-form-control"
                      value={formData.g_name}
                      onChange={(e) => setFormData({ ...formData, g_name: e.target.value })}
                      placeholder="e.g. Abdul Kader"
                      required
                    />
                    {errors.g_name && <div className="innova-form-error">{errors.g_name}</div>}
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-gphone">
                      Guardian Phone <span style={{ color: 'var(--innova-error)' }}>*</span>
                    </label>
                    <input
                      id="student-gphone"
                      type="text"
                      className="innova-form-control"
                      value={formData.g_phone}
                      onChange={(e) => setFormData({ ...formData, g_phone: e.target.value })}
                      placeholder="e.g. 01712000000"
                      required
                    />
                    {errors.g_phone && <div className="innova-form-error">{errors.g_phone}</div>}
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-relg">
                      Relationship <span style={{ color: 'var(--innova-error)' }}>*</span>
                    </label>
                    <select
                      id="student-relg"
                      className="innova-form-control"
                      value={formData.rel_g}
                      onChange={(e) => setFormData({ ...formData, rel_g: e.target.value })}
                      required
                    >
                      {GUARDIAN_RELATION_OPTIONS.map((rel) => (
                        <option key={rel} value={rel}>
                          {rel}
                        </option>
                      ))}
                    </select>
                    {errors.rel_g && <div className="innova-form-error">{errors.rel_g}</div>}
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '0.85rem' }}>
                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-fname">
                      Father's Name
                    </label>
                    <input
                      id="student-fname"
                      type="text"
                      className="innova-form-control"
                      value={formData.f_name || ''}
                      onChange={(e) => setFormData({ ...formData, f_name: e.target.value })}
                    />
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-fphone">
                      Father's Phone
                    </label>
                    <input
                      id="student-fphone"
                      type="text"
                      className="innova-form-control"
                      value={formData.f_phone || ''}
                      onChange={(e) => setFormData({ ...formData, f_phone: e.target.value })}
                    />
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '0.85rem' }}>
                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-mname">
                      Mother's Name
                    </label>
                    <input
                      id="student-mname"
                      type="text"
                      className="innova-form-control"
                      value={formData.m_name || ''}
                      onChange={(e) => setFormData({ ...formData, m_name: e.target.value })}
                    />
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-mphone">
                      Mother's Phone
                    </label>
                    <input
                      id="student-mphone"
                      type="text"
                      className="innova-form-control"
                      value={formData.m_phone || ''}
                      onChange={(e) => setFormData({ ...formData, m_phone: e.target.value })}
                    />
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="student-ref">
                      Reference / Referral Source
                    </label>
                    <input
                      id="student-ref"
                      type="text"
                      className="innova-form-control"
                      value={formData.ref || ''}
                      onChange={(e) => setFormData({ ...formData, ref: e.target.value })}
                      placeholder="e.g. Website, Friend, Social Media"
                    />
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Footer */}
          <div className="innova-modal-footer">
            <button
              type="button"
              className="innova-btn innova-btn-secondary"
              onClick={onClose}
              disabled={isSubmitting}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="innova-btn innova-btn-primary"
              disabled={isSubmitting}
            >
              {isSubmitting
                ? isEditMode
                  ? 'Saving Changes...'
                  : 'Enrolling Student...'
                : isEditMode
                ? 'Update Student'
                : 'Enroll Student'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
