import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import toast, { Toaster } from 'react-hot-toast';

export default function Login() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const [formData, setFormData] = useState({
        email: '',
        password: ''
    });
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);

        const result = await login(formData);
        
        if (result.success) {
            toast.success('Login successful! Welcome back.');
            // Redirect to dashboard using React Router
            navigate('/dashboard');
        } else {
            if (result.error === 'Please verify your email address before logging in.') {
                toast.error('Please verify your email address before logging in.', {
                    duration: 5000,
                    icon: '📧'
                });
            } else {
                toast.error(result.error || 'Login failed. Please try again.');
            }
        }
        
        setLoading(false);
    };

    const handleChange = (e) => {
        setFormData({
            ...formData,
            [e.target.name]: e.target.value
        });
    };

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
                <div className="absolute top-80 right-16 w-1 h-1 bg-purple-200 rounded-full opacity-50 animate-pulse" style={{animationDelay: '6s'}}></div>
                <div className="absolute bottom-40 left-40 w-0.5 h-0.5 bg-white rounded-full opacity-70 animate-pulse" style={{animationDelay: '7s'}}></div>
                
                {/* Planet SVG */}
                <div className="absolute top-16 right-16 opacity-30">
                    <svg width="80" height="80" viewBox="0 0 80 80" className="animate-spin" style={{animationDuration: '20s'}}>
                        <circle cx="40" cy="40" r="30" fill="url(#planetGradient)" />
                        <ellipse cx="40" cy="40" rx="45" ry="8" fill="none" stroke="rgba(255,255,255,0.3)" strokeWidth="1" />
                        <defs>
                            <radialGradient id="planetGradient">
                                <stop offset="0%" stopColor="#4f46e5" />
                                <stop offset="100%" stopColor="#1e1b4b" />
                            </radialGradient>
                        </defs>
                    </svg>
                </div>
                
                {/* Satellite SVG */}
                <div className="absolute bottom-16 left-16 opacity-40">
                    <svg width="60" height="40" viewBox="0 0 60 40" className="animate-bounce" style={{animationDuration: '3s'}}>
                        <rect x="20" y="15" width="20" height="10" fill="#e5e7eb" rx="2" />
                        <rect x="10" y="18" width="8" height="4" fill="#6366f1" />
                        <rect x="42" y="18" width="8" height="4" fill="#6366f1" />
                        <line x1="5" y1="20" x2="15" y2="20" stroke="#9ca3af" strokeWidth="2" />
                        <line x1="45" y1="20" x2="55" y2="20" stroke="#9ca3af" strokeWidth="2" />
                    </svg>
                </div>
                
                {/* Constellation lines */}
                <svg className="absolute inset-0 w-full h-full opacity-20">
                    <line x1="10%" y1="20%" x2="25%" y2="35%" stroke="white" strokeWidth="0.5" />
                    <line x1="25%" y1="35%" x2="40%" y2="25%" stroke="white" strokeWidth="0.5" />
                    <line x1="75%" y1="15%" x2="85%" y2="30%" stroke="white" strokeWidth="0.5" />
                    <line x1="85%" y1="30%" x2="90%" y2="45%" stroke="white" strokeWidth="0.5" />
                </svg>
            </div>
            <div className="max-w-md w-full mx-4">
                <div className="bg-white/95 backdrop-blur-sm rounded-xl shadow-2xl p-8 border border-white/20">
                    <div className="text-center mb-8">
                        <div className="flex items-center justify-center mb-4">
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
                        <p className="text-gray-600">Thermal Analysis Platform</p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">

                        <div>
                            <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-2">
                                Email Address
                            </label>
                            <input
                                type="email"
                                name="email"
                                id="email"
                                required
                                value={formData.email}
                                onChange={handleChange}
                                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 hover:bg-white"
                                placeholder="your@email.com"
                            />
                        </div>

                        <div>
                            <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-2">
                                Password
                            </label>
                            <input
                                type="password"
                                name="password"
                                id="password"
                                required
                                value={formData.password}
                                onChange={handleChange}
                                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 hover:bg-white"
                                placeholder="Your password"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={loading}
                            className="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-4 rounded-lg hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer transform transition-all duration-200 hover:scale-105 shadow-lg"
                        >
                            {loading ? 'Signing in...' : 'Sign In'}
                        </button>
                    </form>

                    <div className="mt-6 text-center">
                        <p className="text-sm text-gray-600">
                            Need an account?{' '}
                            <span className="text-blue-600 font-medium">
                                Contact the administrator
                            </span>
                        </p>
                    </div>

                </div>
            </div>
            <Toaster position="top-right" />
        </div>
    );
}