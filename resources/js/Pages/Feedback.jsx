import React, { useState, useEffect } from 'react';
import DashboardLayout from '../components/DashboardLayout';
import CustomDropdown from '../components/CustomDropdown';
import { MessageSquare, Bug, Lightbulb, Send, Loader2 } from 'lucide-react';
import toast, { Toaster } from 'react-hot-toast';
import { apiClient } from '../utils/api';
import { useAuth } from '../contexts/AuthContext';

export default function Feedback() {
    const { user } = useAuth();

    const [options, setOptions] = useState({
        types: {},
        categories: {},
        priorities: {},
        statuses: {}
    });

    const [submitting, setSubmitting] = useState(false);
    const [formData, setFormData] = useState({
        type: 'general',
        category: '',
        subject: '',
        description: '',
        priority: 'medium',
        contact_email: user?.email || ''
    });
    const [errors, setErrors] = useState({});

    useEffect(() => {
        fetchFeedbackOptions();
    }, []);

    const fetchFeedbackOptions = async () => {
        try {
            const response = await apiClient.get('/feedback/options');
            setOptions(response.data);
        } catch (err) {
            console.error('Failed to fetch feedback options:', err);
            toast.error('Failed to load feedback options');
        }
    };

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({ ...prev, [name]: value }));
        // Clear error when user starts typing
        if (errors[name]) {
            setErrors(prev => ({ ...prev, [name]: '' }));
        }
    };

    const handleDropdownChange = (field, value) => {
        setFormData(prev => ({ ...prev, [field]: value }));
        // Clear error when user selects
        if (errors[field]) {
            setErrors(prev => ({ ...prev, [field]: '' }));
        }
    };

    const validateForm = () => {
        const newErrors = {};
        
        if (!formData.subject.trim()) {
            newErrors.subject = 'Subject is required';
        }
        
        if (!formData.description.trim()) {
            newErrors.description = 'Description is required';
        } else if (formData.description.length > 5000) {
            newErrors.description = 'Description must not exceed 5000 characters';
        }
        
        // Improved email validation regex
        if (formData.contact_email && !/^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/.test(formData.contact_email)) {
            newErrors.contact_email = 'Please enter a valid email address';
        }
        
        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        
        if (!validateForm()) {
            return;
        }
        
        setSubmitting(true);
        
        try {
            await apiClient.post('/feedback', formData);
            toast.success('Feedback submitted successfully!');
            setFormData({
                type: 'general',
                category: '',
                subject: '',
                description: '',
                priority: 'medium',
                contact_email: user?.email || ''
            });

        } catch (err) {
            console.error('Failed to submit feedback:', err);
            if (err.response?.data?.errors) {
                setErrors(err.response.data.errors);
            } else {
                toast.error('Failed to submit feedback. Please try again.');
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <DashboardLayout title="Feedback">
            <Toaster position="top-right" />
            
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <p className="text-gray-600">
                        Share your feedback, report bugs, or suggest new features for the MELT-B platform.
                    </p>
                </div>

                {/* Feedback Form */}
                <div className="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100 mb-10">
                    <div className="mb-6">
                        <h3 className="text-lg font-semibold text-gray-900 mb-2">Submit New Feedback</h3>
                    </div>
                        
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* Type */}
                                <CustomDropdown
                                    label="Type"
                                    value={formData.type}
                                    options={options.types}
                                    placeholder="Select type"
                                    onChange={(value) => handleDropdownChange('type', value)}
                                    error={errors.type}
                                    required
                                />

                                {/* Category */}
                                <CustomDropdown
                                    label="Category"
                                    value={formData.category}
                                    options={options.categories}
                                    placeholder="Select a category"
                                    onChange={(value) => handleDropdownChange('category', value)}
                                    error={errors.category}
                                />
                            </div>

                            {/* Subject */}
                            <div>
                                <label htmlFor="subject" className="block text-sm font-medium text-gray-700 mb-1">
                                    Subject *
                                </label>
                                <input
                                    type="text"
                                    id="subject"
                                    name="subject"
                                    value={formData.subject}
                                    onChange={handleInputChange}
                                    className={`block w-full px-3 py-2 text-gray-900 bg-white border rounded-md shadow-sm placeholder-gray-500 focus:outline-none focus:ring-1 transition-colors duration-150 hover:border-gray-400 ${
                                        errors.subject 
                                            ? 'border-red-300 focus:border-red-500 focus:ring-red-500' 
                                            : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500'
                                    }`}
                                    placeholder="Brief summary of your feedback"
                                />
                                {errors.subject && (
                                    <p className="mt-1 text-sm text-red-600">{errors.subject}</p>
                                )}
                            </div>

                            {/* Description */}
                            <div>
                                <div className="flex justify-between items-center mb-1">
                                    <label htmlFor="description" className="block text-sm font-medium text-gray-700">
                                        Description *
                                    </label>
                                    <span className={`text-xs ${
                                        formData.description.length > 5000 ? 'text-red-600' : 
                                        formData.description.length > 4500 ? 'text-yellow-600' : 'text-gray-500'
                                    }`}>
                                        {formData.description.length}/5000
                                    </span>
                                </div>
                                <textarea
                                    id="description"
                                    name="description"
                                    rows={4}
                                    value={formData.description}
                                    onChange={handleInputChange}
                                    className={`block w-full px-4 py-3 text-gray-900 bg-white border rounded-lg shadow-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 hover:border-gray-400 resize-none ${
                                        errors.description ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-300'
                                    }`}
                                    placeholder="Detailed description of your feedback, bug report, or feature request"
                                    required
                                />
                                {errors.description && (
                                    <p className="mt-1 text-sm text-red-600">{errors.description}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* Priority */}
                                <CustomDropdown
                                    label="Priority"
                                    value={formData.priority}
                                    options={options.priorities}
                                    placeholder="Select priority"
                                    onChange={(value) => handleDropdownChange('priority', value)}
                                    error={errors.priority}
                                />

                                {/* Contact Email */}
                                <div>
                                    <label htmlFor="contact_email" className="block text-sm font-medium text-gray-700 mb-1">
                                        Contact Email
                                    </label>
                                    <input
                                        type="email"
                                        id="contact_email"
                                        name="contact_email"
                                        value={formData.contact_email}
                                        onChange={handleInputChange}
                                        className={`block w-full px-3 py-2 text-gray-900 bg-white border rounded-md shadow-sm placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-150 hover:border-gray-400 ${
                                            errors.contact_email ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-300'
                                        }`}
                                        placeholder="Optional: for follow-up questions"
                                    />
                                    {errors.contact_email && (
                                        <p className="mt-1 text-sm text-red-600">{errors.contact_email}</p>
                                    )}
                                </div>
                            </div>

                            {/* Submit Button */}
                            <div className="flex justify-end pt-6 border-t border-gray-200">
                                <button
                                    type="submit"
                                    disabled={submitting}
                                    className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200 cursor-pointer"
                                >
                                    {submitting ? (
                                        <>
                                            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                            Submitting...
                                        </>
                                    ) : (
                                        <>
                                            <Send className="h-4 w-4 mr-2" />
                                            Submit Feedback
                                        </>
                                    )}
                                </button>
                            </div>
                        </form>
                    </div>


            </div>
        </DashboardLayout>
    );
}