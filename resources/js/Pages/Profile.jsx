import React from 'react';
import DashboardLayout from '../components/DashboardLayout';
import { useAuth } from '../contexts/AuthContext';

export default function Profile() {
    const { user } = useAuth();

    const formatDate = (dateString) => {
        if (!dateString) return 'Not verified';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };

    return (
        <DashboardLayout title="User Profile">
            <div className="max-w-4xl mx-auto space-y-6">
                {/* Profile Information */}
                <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="px-4 py-5 sm:p-6">
                        <div className="flex items-center space-x-4 mb-6">
                            <div className="h-16 w-16 rounded-full bg-blue-500 flex items-center justify-center">
                                <span className="text-2xl font-medium text-white">
                                    {user?.name?.charAt(0).toUpperCase()}
                                </span>
                            </div>
                            <div>
                                <h3 className="text-lg font-medium text-gray-900">{user?.name}</h3>
                                <p className="text-sm text-gray-500 capitalize">{user?.role} Account</p>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Email</label>
                                <div className="mt-1 text-sm text-gray-900">{user?.email}</div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">Role</label>
                                <div className="mt-1">
                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 capitalize">
                                        {user?.role}
                                    </span>
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">Email Verification</label>
                                <div className="mt-1">
                                    {user?.email_verified_at ? (
                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Verified {formatDate(user.email_verified_at)}
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Not Verified
                                        </span>
                                    )}
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">Member Since</label>
                                <div className="mt-1 text-sm text-gray-900">
                                    {formatDate(user?.created_at)}
                                </div>
                            </div>
                        </div>

                        {/* Contact Information */}
                        {user?.contact_info && (
                            <div className="mt-6 pt-6 border-t border-gray-200">
                                <h4 className="text-lg font-medium text-gray-900 mb-4">Contact Information</h4>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    {user.contact_info.phone && (
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Phone</label>
                                            <div className="mt-1 text-sm text-gray-900">{user.contact_info.phone}</div>
                                        </div>
                                    )}
                                    {user.contact_info.company && (
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Company</label>
                                            <div className="mt-1 text-sm text-gray-900">{user.contact_info.company}</div>
                                        </div>
                                    )}
                                    {user.contact_info.department && (
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Department</label>
                                            <div className="mt-1 text-sm text-gray-900">{user.contact_info.department}</div>
                                        </div>
                                    )}
                                    {user.contact_info.address && (
                                        <div className="md:col-span-2">
                                            <label className="block text-sm font-medium text-gray-700">Address</label>
                                            <div className="mt-1 text-sm text-gray-900">{user.contact_info.address}</div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Actions */}
                <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="px-4 py-5 sm:p-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">Account Actions</h3>
                        <div className="space-y-3">
                            <div className="bg-blue-50 p-4 rounded-md">
                                <div className="text-sm text-blue-700">
                                    <strong>Profile Management:</strong> Full profile editing functionality will be implemented in a future update. 
                                    For now, contact your administrator to update your profile information.
                                </div>
                            </div>

                            <div className="bg-yellow-50 p-4 rounded-md">
                                <div className="text-sm text-yellow-700">
                                    <strong>API Access:</strong> API token management and generation features will be available in the downloads section 
                                    for programmatic access to your authorized data.
                                </div>
                            </div>

                            {!user?.email_verified_at && (
                                <div className="bg-red-50 p-4 rounded-md">
                                    <div className="text-sm text-red-700">
                                        <strong>Email Verification:</strong> Please verify your email address to access all platform features. 
                                        Check your email for verification instructions.
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
} 