import React, { useEffect, useState } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import { CheckCircle, XCircle, Mail, ArrowLeft } from 'lucide-react';
import toast, { Toaster } from 'react-hot-toast';

export default function EmailVerificationResult() {
    const [searchParams] = useSearchParams();
    const [status, setStatus] = useState('loading');
    const [message, setMessage] = useState('');

    useEffect(() => {
        const success = searchParams.get('success');
        const error = searchParams.get('error');
        const verified = searchParams.get('verified');

        if (success === 'true' || verified === 'true') {
            setStatus('success');
            setMessage('Your email has been successfully verified! You can now access all features of your account.');
            toast.success('Email verified successfully! You can now access all features of your account.');
        } else if (error) {
            setStatus('error');
            setMessage(decodeURIComponent(error));
            toast.error(decodeURIComponent(error));
        } else {
            setStatus('error');
            setMessage('Invalid verification link or the link has expired.');
            toast.error('Invalid verification link or the link has expired.');
        }
    }, [searchParams]);

    return (
        <div className="min-h-screen flex items-center justify-center relative overflow-hidden" style={{
            background: 'radial-gradient(ellipse at center, #1a1a2e 0%, #16213e 35%, #0f0f23 100%)'
        }}>
            {/* Space background with stars */}
            <div className="absolute inset-0 pointer-events-none">
                {/* Animated stars */}
                <div className="absolute top-10 left-10 w-1 h-1 bg-white rounded-full opacity-80 animate-pulse"></div>
                <div className="absolute top-20 right-20 w-0.5 h-0.5 bg-blue-200 rounded-full opacity-60 animate-pulse" style={{animationDelay: '1s'}}></div>
                <div className="absolute bottom-32 left-16 w-1.5 h-1.5 bg-yellow-100 rounded-full opacity-70 animate-pulse" style={{animationDelay: '2s'}}></div>
                <div className="absolute top-40 right-32 w-0.5 h-0.5 bg-white rounded-full opacity-50 animate-pulse" style={{animationDelay: '3s'}}></div>
                <div className="absolute bottom-20 right-40 w-1 h-1 bg-blue-100 rounded-full opacity-40 animate-pulse" style={{animationDelay: '4s'}}></div>
                <div className="absolute top-60 left-32 w-0.5 h-0.5 bg-white rounded-full opacity-60 animate-pulse" style={{animationDelay: '5s'}}></div>
            </div>

            <div className="max-w-md w-full mx-4">
                <div className="bg-white/95 backdrop-blur-sm rounded-xl shadow-2xl p-8 border border-white/20">
                    <div className="text-center">
                        <div className="flex items-center justify-center mb-6">
                            <svg width="40" height="40" viewBox="0 0 40 40" className="mr-3">
                                <circle cx="20" cy="20" r="18" fill="url(#logoGradient)" />
                                <circle cx="20" cy="20" r="12" fill="none" stroke="white" strokeWidth="2" opacity="0.8" />
                                <circle cx="20" cy="20" r="6" fill="white" opacity="0.9" />
                                <defs>
                                    <radialGradient id="logoGradient">
                                        <stop offset="0%" stopColor="#3b82f6" />
                                        <stop offset="100%" stopColor="#1e40af" />
                                    </radialGradient>
                                </defs>
                            </svg>
                            <h1 className="text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">MELT-B</h1>
                        </div>

                        {status === 'loading' && (
                            <div className="space-y-4">
                                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                                <p className="text-gray-600">Verifying your email...</p>
                            </div>
                        )}

                        {status === 'success' && (
                            <div className="space-y-4">
                                <div className="flex justify-center">
                                    <CheckCircle className="h-16 w-16 text-green-500" />
                                </div>
                                <h2 className="text-2xl font-bold text-gray-900">Email Verified!</h2>
                                <p className="text-gray-600">{message}</p>
                                <div className="pt-4">
                                    <Link
                                        to="/login"
                                        className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-200 transform hover:scale-105 shadow-lg"
                                    >
                                        <ArrowLeft className="h-4 w-4 mr-2" />
                                        Continue to Login
                                    </Link>
                                </div>
                            </div>
                        )}

                        {status === 'error' && (
                            <div className="space-y-4">
                                <div className="flex justify-center">
                                    <XCircle className="h-16 w-16 text-red-500" />
                                </div>
                                <h2 className="text-2xl font-bold text-gray-900">Verification Failed</h2>
                                <p className="text-gray-600">{message}</p>
                                <div className="pt-4 space-y-3">
                                    <Link
                                        to="/login"
                                        className="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-200 transform hover:scale-105 shadow-lg"
                                    >
                                        <ArrowLeft className="h-4 w-4 mr-2" />
                                        Back to Login
                                    </Link>
                                    <div className="text-sm text-gray-500">
                                        <p>Need help? Contact support or try requesting a new verification email.</p>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
            <Toaster position="top-right" />
        </div>
    );
}