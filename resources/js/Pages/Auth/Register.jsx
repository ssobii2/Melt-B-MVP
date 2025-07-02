import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

export default function Register() {
    const { register } = useAuth();
    const [formData, setFormData] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'user'
    });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [errors, setErrors] = useState({});
    const [success, setSuccess] = useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError('');
        setErrors({});
        setSuccess('');

        const result = await register(formData);
        
        if (result.success) {
            setSuccess(result.message);
            setFormData({
                name: '',
                email: '',
                password: '',
                password_confirmation: '',
                role: 'user'
            });
        } else {
            setError(result.error);
            setErrors(result.errors || {});
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
        <div className="min-h-screen flex items-center justify-center relative overflow-hidden py-12" style={{
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
                <div className="absolute top-32 left-80 w-0.5 h-0.5 bg-cyan-200 rounded-full opacity-60 animate-pulse" style={{animationDelay: '8s'}}></div>
                <div className="absolute bottom-60 right-20 w-1 h-1 bg-pink-200 rounded-full opacity-50 animate-pulse" style={{animationDelay: '9s'}}></div>
                
                {/* Moon SVG */}
                <div className="absolute top-20 left-20 opacity-25">
                    <svg width="60" height="60" viewBox="0 0 60 60" className="animate-pulse" style={{animationDuration: '4s'}}>
                        <circle cx="30" cy="30" r="25" fill="url(#moonGradient)" />
                        <circle cx="22" cy="20" r="3" fill="rgba(0,0,0,0.2)" />
                        <circle cx="35" cy="25" r="2" fill="rgba(0,0,0,0.15)" />
                        <circle cx="28" cy="35" r="4" fill="rgba(0,0,0,0.1)" />
                        <defs>
                            <radialGradient id="moonGradient">
                                <stop offset="0%" stopColor="#f3f4f6" />
                                <stop offset="100%" stopColor="#d1d5db" />
                            </radialGradient>
                        </defs>
                    </svg>
                </div>
                
                {/* Space Station SVG */}
                <div className="absolute bottom-24 right-24 opacity-35">
                    <svg width="70" height="50" viewBox="0 0 70 50" className="animate-bounce" style={{animationDuration: '4s'}}>
                        <rect x="25" y="20" width="20" height="10" fill="#e5e7eb" rx="2" />
                        <rect x="15" y="22" width="10" height="6" fill="#3b82f6" rx="1" />
                        <rect x="45" y="22" width="10" height="6" fill="#3b82f6" rx="1" />
                        <rect x="30" y="15" width="10" height="5" fill="#ef4444" />
                        <line x1="5" y1="25" x2="20" y2="25" stroke="#6b7280" strokeWidth="2" />
                        <line x1="50" y1="25" x2="65" y2="25" stroke="#6b7280" strokeWidth="2" />
                        <circle cx="8" cy="25" r="3" fill="#fbbf24" />
                        <circle cx="62" cy="25" r="3" fill="#fbbf24" />
                    </svg>
                </div>
                
                {/* Rocket SVG */}
                <div className="absolute top-60 right-10 opacity-30">
                    <svg width="40" height="60" viewBox="0 0 40 60" className="animate-pulse" style={{animationDuration: '3s'}}>
                        <polygon points="20,5 15,25 25,25" fill="#ef4444" />
                        <rect x="15" y="25" width="10" height="20" fill="#e5e7eb" />
                        <polygon points="12,45 15,50 25,50 28,45" fill="#f59e0b" />
                        <circle cx="18" cy="30" r="1.5" fill="#3b82f6" />
                        <circle cx="22" cy="35" r="1.5" fill="#3b82f6" />
                    </svg>
                </div>
                
                {/* Constellation lines */}
                <svg className="absolute inset-0 w-full h-full opacity-15">
                    <line x1="15%" y1="25%" x2="30%" y2="40%" stroke="white" strokeWidth="0.5" />
                    <line x1="30%" y1="40%" x2="45%" y2="30%" stroke="white" strokeWidth="0.5" />
                    <line x1="70%" y1="20%" x2="80%" y2="35%" stroke="white" strokeWidth="0.5" />
                    <line x1="80%" y1="35%" x2="85%" y2="50%" stroke="white" strokeWidth="0.5" />
                    <line x1="20%" y1="70%" x2="35%" y2="80%" stroke="white" strokeWidth="0.5" />
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
                        <p className="text-gray-600">Create Your Account</p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {error && (
                            <div className="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded">
                                {error}
                            </div>
                        )}

                        {success && (
                            <div className="bg-green-50 border border-green-300 text-green-700 px-4 py-3 rounded">
                                {success}
                            </div>
                        )}

                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-2">
                                Full Name
                            </label>
                            <input
                                type="text"
                                name="name"
                                id="name"
                                required
                                value={formData.name}
                                onChange={handleChange}
                                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 hover:bg-white"
                                placeholder="Your full name"
                            />
                            {errors.name && (
                                <p className="mt-1 text-sm text-red-600">{errors.name[0]}</p>
                            )}
                        </div>

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
                            {errors.email && (
                                <p className="mt-1 text-sm text-red-600">{errors.email[0]}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="role" className="block text-sm font-medium text-gray-700 mb-2">
                                Role
                            </label>
                            <select
                                name="role"
                                id="role"
                                value={formData.role}
                                onChange={handleChange}
                                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 hover:bg-white"
                            >
                                <option value="user">General User</option>
                                <option value="researcher">Researcher</option>
                                <option value="contractor">Contractor</option>
                                <option value="municipality">Municipality</option>
                            </select>
                            {errors.role && (
                                <p className="mt-1 text-sm text-red-600">{errors.role[0]}</p>
                            )}
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
                                placeholder="Create a strong password"
                            />
                            {errors.password && (
                                <p className="mt-1 text-sm text-red-600">{errors.password[0]}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700 mb-2">
                                Confirm Password
                            </label>
                            <input
                                type="password"
                                name="password_confirmation"
                                id="password_confirmation"
                                required
                                value={formData.password_confirmation}
                                onChange={handleChange}
                                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 hover:bg-white"
                                placeholder="Confirm your password"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={loading}
                            className="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-4 rounded-lg hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer transform transition-all duration-200 hover:scale-105 shadow-lg"
                        >
                            {loading ? 'Creating Account...' : 'Create Account'}
                        </button>
                    </form>

                    <div className="mt-6 text-center">
                        <p className="text-sm text-gray-600">
                            Already have an account?{' '}
                            <Link to="/login" className="text-blue-600 hover:text-blue-500 font-medium cursor-pointer">
                                Sign in
                            </Link>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}