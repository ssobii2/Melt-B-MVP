import React, { useState, useEffect } from 'react';
import DashboardLayout from '../components/DashboardLayout';
import { useAuth } from '../contexts/AuthContext';
import { User, Mail, Shield, Calendar, CheckCircle, XCircle, Key, Copy, Eye, EyeOff, Loader2, AlertCircle } from 'lucide-react';
import toast, { Toaster } from 'react-hot-toast';
import { apiClient } from '../utils/api';

export default function Profile() {
    const { user: authUser, token } = useAuth();
    const [user, setUser] = useState(authUser);
    const [profileLoading, setProfileLoading] = useState(false);
    const [passwordLoading, setPasswordLoading] = useState(false);
    const [tokenLoading, setTokenLoading] = useState(false);
    const [revokeLoading, setRevokeLoading] = useState(false);
    // Removed message and error state - using toast notifications instead
    
    // Profile form state
    const [profileForm, setProfileForm] = useState({
        name: authUser.name,
        email: authUser.email
    });
    
    // Password form state
    const [passwordForm, setPasswordForm] = useState({
        current_password: '',
        password: '',
        password_confirmation: ''
    });
    const [showPasswords, setShowPasswords] = useState({
        current: false,
        new: false,
        confirm: false
    });
    
    // API Token state
    const [generatedToken, setGeneratedToken] = useState('');
    const [showToken, setShowToken] = useState(false);
    const [existingTokens, setExistingTokens] = useState([]);
    const [tokensLoading, setTokensLoading] = useState(false);
    const [tokenCopied, setTokenCopied] = useState(false);

    useEffect(() => {
        if (authUser) {
            setUser(authUser);
            fetchExistingTokens();
        }
    }, [authUser]);

    const fetchExistingTokens = async () => {
        setTokensLoading(true);
        try {
            const response = await apiClient.get('/tokens');
            // Filter out admin-dashboard and Login Token tokens
            const filteredTokens = (response.data.tokens || []).filter(token => 
                token.name !== 'admin-dashboard' && token.name !== 'Login Token'
            );
            setExistingTokens(filteredTokens);
        } catch (error) {
            console.error('Error fetching tokens:', error);
        } finally {
            setTokensLoading(false);
        }
    };

    const handleRevokeToken = async (tokenId) => {
        setRevokeLoading(true);
        try {
            await apiClient.delete(`/tokens/${tokenId}`);
            toast.success('API token revoked successfully!');
            fetchExistingTokens(); // Refresh the list
            // Hide the generated token display when any token is revoked
            setGeneratedToken('');
            setShowToken(false);
            setTokenCopied(false);
        } catch (error) {
            const errorMsg = error.response?.data?.message || 'Failed to revoke token';
            toast.error(errorMsg);
        } finally {
            setRevokeLoading(false);
        }
    };

    const formatRole = (role) => {
        return role.charAt(0).toUpperCase() + role.slice(1);
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'Not available';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };

    // Removed showMessage function - using toast notifications instead

    const handleProfileUpdate = async (e) => {
        e.preventDefault();
        setProfileLoading(true);
        
        try {
            const response = await apiClient.put('/user/profile-information', profileForm);
            setUser(response.data.user);
            toast.success('Profile updated successfully!');
        } catch (err) {
            const errorMsg = err.response?.data?.message || 'Failed to update profile';
            toast.error(errorMsg);
        } finally {
            setProfileLoading(false);
        }
    };

    const handlePasswordUpdate = async (e) => {
        e.preventDefault();
        setPasswordLoading(true);
        
        try {
            await apiClient.put('/user/password', passwordForm);
            
            setPasswordForm({
                current_password: '',
                password: '',
                password_confirmation: ''
            });
            toast.success('Password updated successfully!');
        } catch (err) {
            const errorMsg = err.response?.data?.message || 'Failed to update password';
            toast.error(errorMsg);
        } finally {
            setPasswordLoading(false);
        }
    };

    const handleGenerateToken = async (e) => {
        e.preventDefault();
        setTokenLoading(true);
        
        try {
            const response = await apiClient.post('/tokens/generate', {});
            
            setGeneratedToken(response.data.token);
            setShowToken(true);
            setTokenCopied(false); // Reset copied state for new token
            toast.success('API token generated successfully! Make sure to copy it now.');
            fetchExistingTokens(); // Refresh the tokens list
        } catch (err) {
            const errorMsg = err.response?.data?.message || 'Failed to generate token';
            toast.error(errorMsg);
        } finally {
            setTokenLoading(false);
        }
    };

    const copyToClipboard = async (text) => {
        try {
            await navigator.clipboard.writeText(text);
            toast.success('Token copied to clipboard!');
            setTokenCopied(true);
        } catch (err) {
            console.error('Failed to copy:', err);
            toast.error('Failed to copy to clipboard');
        }
    };

    const togglePasswordVisibility = (field) => {
        setShowPasswords(prev => ({
            ...prev,
            [field]: !prev[field]
        }));
    };

    return (
        <DashboardLayout>
            <Toaster position="top-right" />
            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    {/* Messages */}
                    {/* Toast notifications will handle success and error messages */}

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Basic Information */}
                        <div className="bg-white border border-gray-200 rounded-lg shadow-sm">
                            <div className="px-6 py-4 border-b border-gray-200">
                                <h3 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <User className="h-5 w-5" />
                                    Basic Information
                                </h3>
                                <p className="text-sm text-gray-600 mt-1">
                                    Your account details and contact information.
                                </p>
                            </div>
                            <div className="px-6 py-4 space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-gray-700">Role</label>
                                    <div className="flex items-center gap-2">
                                        <Shield className="h-4 w-4 text-gray-500" />
                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border border-gray-300 bg-white text-gray-700">{formatRole(user.role)}</span>
                                    </div>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-700">Email Verification</label>
                                    <div className="flex items-center gap-2">
                                        {user.email_verified_at ? (
                                            <>
                                                <CheckCircle className="h-4 w-4 text-green-600" />
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Verified
                                                </span>
                                            </>
                                        ) : (
                                            <>
                                                <XCircle className="h-4 w-4 text-red-600" />
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    Not Verified
                                                </span>
                                            </>
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-700">Member Since</label>
                                    <div className="flex items-center gap-2">
                                        <Calendar className="h-4 w-4 text-gray-500" />
                                        <p className="text-sm text-gray-900">{formatDate(user.created_at)}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Update Profile */}
                        <div className="bg-white border border-gray-200 rounded-lg shadow-sm">
                            <div className="px-6 py-4 border-b border-gray-200">
                                <h3 className="text-lg font-semibold text-gray-900">Update Profile</h3>
                                <p className="text-sm text-gray-600 mt-1">
                                    Update your name and email address.
                                </p>
                            </div>
                            <div className="px-6 py-4">
                                <form onSubmit={handleProfileUpdate} className="space-y-4">
                                    <div>
                                        <label htmlFor="name" className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">Name</label>
                                        <input
                                            id="name"
                                            type="text"
                                            value={profileForm.name}
                                            onChange={(e) => setProfileForm(prev => ({ ...prev, name: e.target.value }))}
                                            required
                                            className="flex h-10 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                    </div>
                                    <div>
                                        <label htmlFor="email" className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">Email</label>
                                        <input
                                            id="email"
                                            type="email"
                                            value={profileForm.email}
                                            onChange={(e) => setProfileForm(prev => ({ ...prev, email: e.target.value }))}
                                            required
                                            className="flex h-10 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                    </div>
                                    <button type="submit" disabled={profileLoading} className="inline-flex items-center justify-center rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500 px-4 py-2 text-sm cursor-pointer">
                                    {profileLoading ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : null}
                                    Update Profile
                                </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    {/* Change Password */}
                    <div className="bg-white border border-gray-200 rounded-lg shadow-sm">
                        <div className="px-6 py-4 border-b border-gray-200">
                            <h3 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                <Key className="h-5 w-5" />
                                Change Password
                            </h3>
                            <p className="text-sm text-gray-600 mt-1">
                                Update your account password for better security.
                            </p>
                        </div>
                        <div className="px-6 py-4">
                            <form onSubmit={handlePasswordUpdate} className="space-y-4 max-w-md">
                                <div>
                                    <label htmlFor="current_password" className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">Current Password</label>
                                    <div className="relative">
                                        <input
                                            id="current_password"
                                            type={showPasswords.current ? "text" : "password"}
                                            value={passwordForm.current_password}
                                            onChange={(e) => setPasswordForm(prev => ({ ...prev, current_password: e.target.value }))}
                                            required
                                            className="flex h-10 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                        <button
                                            type="button"
                                            className="inline-flex items-center justify-center rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none text-gray-700 hover:bg-gray-100 focus:ring-blue-500 px-3 py-1.5 text-xs absolute right-0 top-0 h-full"
                                            onClick={() => togglePasswordVisibility('current')}
                                        >
                                            {showPasswords.current ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label htmlFor="password" className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">New Password</label>
                                    <div className="relative">
                                        <input
                                            id="password"
                                            type={showPasswords.new ? "text" : "password"}
                                            value={passwordForm.password}
                                            onChange={(e) => setPasswordForm(prev => ({ ...prev, password: e.target.value }))}
                                            required
                                            className="flex h-10 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                        <button
                                            type="button"
                                            className="inline-flex items-center justify-center rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none text-gray-700 hover:bg-gray-100 focus:ring-blue-500 px-3 py-1.5 text-xs absolute right-0 top-0 h-full"
                                            onClick={() => togglePasswordVisibility('new')}
                                        >
                                            {showPasswords.new ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label htmlFor="password_confirmation" className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">Confirm New Password</label>
                                    <div className="relative">
                                        <input
                                            id="password_confirmation"
                                            type={showPasswords.confirm ? "text" : "password"}
                                            value={passwordForm.password_confirmation}
                                            onChange={(e) => setPasswordForm(prev => ({ ...prev, password_confirmation: e.target.value }))}
                                            required
                                            className="flex h-10 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                        <button
                                            type="button"
                                            className="inline-flex items-center justify-center rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none text-gray-700 hover:bg-gray-100 focus:ring-blue-500 px-3 py-1.5 text-xs absolute right-0 top-0 h-full"
                                            onClick={() => togglePasswordVisibility('confirm')}
                                        >
                                            {showPasswords.confirm ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" disabled={passwordLoading} className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500 h-10 py-2 px-4 cursor-pointer">
                                    {passwordLoading ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : null}
                                    Update Password
                                </button>
                            </form>
                        </div>
                    </div>

                    {/* API Token Management */}
                    <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div className="flex flex-col space-y-1.5 p-6">
                            <h3 className="text-lg font-semibold leading-none tracking-tight flex items-center gap-2">
                                <Key className="h-5 w-5" />
                                API Token Management
                            </h3>
                            <p className="text-sm text-gray-600">
                                Generate API tokens for programmatic access to your data.
                            </p>
                        </div>
                        <div className="p-6 pt-0 space-y-6">
                            {/* Generate New Token */}
                            <div>
                                <h4 className="text-sm font-medium mb-3">Generate New Token</h4>
                                <div className="space-y-4">
                                    {existingTokens.length === 0 && (
                                        <form onSubmit={handleGenerateToken} className="space-y-4">
                                            <p className="text-sm text-gray-600 mb-4">
                                                Generate a new API token for accessing the API. You can only have one active token at a time.
                                            </p>
                                            <button type="submit" disabled={tokenLoading} className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500 h-10 py-2 px-4 cursor-pointer">
                                                {tokenLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Generate API Token'}
                                            </button>
                                        </form>
                                    )}
                                    {existingTokens.length > 0 && (
                                        <div className="bg-yellow-50 border border-yellow-200 rounded-md p-3">
                                            <p className="text-sm text-yellow-800">
                                                <strong>Note:</strong> You already have an active API token. Please revoke it first if you need to generate a new one.
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Display Generated Token */}
                            {generatedToken && (
                                <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                    <h4 className="text-sm font-medium text-yellow-800 mb-2">Your New API Token</h4>
                                    <p className="text-xs text-yellow-700 mb-3">
                                        Make sure to copy this token now. You won't be able to see it again!
                                    </p>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type={showToken ? "text" : "password"}
                                            value={generatedToken}
                                            readOnly
                                            className="flex h-10 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:cursor-not-allowed disabled:opacity-50 font-mono"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowToken(!showToken)}
                                            className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none border border-gray-300 bg-white hover:bg-gray-50 focus:ring-blue-500 h-9 px-3 cursor-pointer"
                                        >
                                            {showToken ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => copyToClipboard(generatedToken)}
                                            className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none border border-gray-300 bg-white hover:bg-gray-50 focus:ring-blue-500 h-9 px-3 cursor-pointer"
                                        >
                                            <Copy className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            )}

                            <div className="shrink-0 bg-gray-200 h-[1px] w-full"></div>

                            {/* Existing API Tokens */}
                            {(!generatedToken || tokenCopied) && (
                            <div>
                                <h4 className="text-sm font-medium mb-3">Existing API Tokens</h4>
                                {tokensLoading ? (
                                    <div className="flex items-center justify-center py-4">
                                        <Loader2 className="w-6 h-6 animate-spin text-gray-500" />
                                        <span className="ml-2 text-gray-500">Loading tokens...</span>
                                    </div>
                                ) : existingTokens.length > 0 ? (
                                    <div className="space-y-3">
                                        {existingTokens.map((token) => (
                                            <div key={token.id} className="flex items-center justify-between p-3 border border-gray-200 rounded-md">
                                                <div>
                                                    <p className="font-medium text-gray-900">{token.name}</p>
                                                    <p className="text-sm text-gray-500">
                                                        Created: {new Date(token.created_at).toLocaleDateString()}
                                                    </p>
                                                    {token.last_used_at && (
                                                        <p className="text-sm text-gray-500">
                                                            Last used: {new Date(token.last_used_at).toLocaleDateString()}
                                                        </p>
                                                    )}
                                                </div>
                                                <button
                                                    onClick={() => handleRevokeToken(token.id)}
                                                    disabled={revokeLoading}
                                                    className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 h-9 px-3 cursor-pointer"
                                                >
                                                    {revokeLoading ? (
                                                        <Loader2 className="w-3 h-3 animate-spin" />
                                                    ) : (
                                                        'Revoke'
                                                    )}
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-gray-500 text-center py-4">No API tokens found</p>
                                )}
                            </div>
                            )}

                            <div className="shrink-0 bg-gray-200 h-[1px] w-full"></div>

                            {/* Token Usage Instructions */}
                            <div className="bg-gray-50 p-4 rounded-lg">
                                <h4 className="text-sm font-medium mb-3">Using Your API Token</h4>
                                <div className="space-y-4 text-sm text-gray-600">
                                    <div>
                                        <p className="font-medium text-gray-800 mb-2">Authentication</p>
                                        <p className="mb-2">
                                            Include your API token in the Authorization header of all API requests:
                                        </p>
                                        <div className="bg-gray-100 p-3 rounded font-mono text-xs">
                                            Authorization: Bearer YOUR_TOKEN_HERE
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <p className="font-medium text-gray-800 mb-2">Available Endpoints</p>
                                        <div className="space-y-2">
                                            <div>
                                                <p className="font-medium">Get your entitlements:</p>
                                                <div className="bg-gray-100 p-2 rounded font-mono text-xs">
                                                    GET {window.location.origin}/api/me/entitlements
                                                </div>
                                            </div>
                                            <div>
                                                <p className="font-medium">Get building data:</p>
                                                <div className="bg-gray-100 p-2 rounded font-mono text-xs">
                                                    GET {window.location.origin}/api/buildings
                                                </div>
                                            </div>
                                            <div>
                                                <p className="font-medium">Download dataset:</p>
                                                <div className="bg-gray-100 p-2 rounded font-mono text-xs">
                                                    GET {window.location.origin}/api/downloads/{'{entitlement_id}'}?format=csv
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <p className="font-medium text-gray-800 mb-2">Example Usage (cURL)</p>
                                        <div className="bg-gray-100 p-3 rounded font-mono text-xs overflow-x-auto">
                                            curl -H "Authorization: Bearer YOUR_TOKEN_HERE" \
                                            <br />     -H "Accept: application/json" \
                                            <br />     {window.location.origin}/api/me/entitlements
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <p className="font-medium text-gray-800 mb-2">Example Usage (JavaScript)</p>
                                        <div className="bg-gray-100 p-3 rounded font-mono text-xs overflow-x-auto">
                                            {`fetch('${window.location.origin}/api/buildings', {
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN_HERE',
    'Accept': 'application/json'
  }
})
.then(response => response.json())
.then(data => console.log(data));`}
                                        </div>
                                    </div>
                                    
                                    <div className="bg-blue-50 border border-blue-200 rounded p-3">
                                        <p className="text-blue-800 text-xs">
                                            <strong>Security Note:</strong> Keep your API token secure and never share it publicly. 
                                            If you suspect your token has been compromised, revoke it immediately and generate a new one.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}