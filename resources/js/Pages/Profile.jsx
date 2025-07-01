import React, { useState, useEffect } from 'react';
import DashboardLayout from '../components/DashboardLayout';
import { useAuth } from '../contexts/AuthContext';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/card';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Badge } from '../components/ui/badge';
import { Separator } from '../components/ui/separator';
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
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <User className="h-5 w-5" />
                                    Basic Information
                                </CardTitle>
                                <CardDescription>
                                    Your account details and contact information.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-gray-700">Role</label>
                                    <div className="flex items-center gap-2">
                                        <Shield className="h-4 w-4 text-gray-500" />
                                        <Badge variant="outline">{formatRole(user.role)}</Badge>
                                    </div>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-700">Email Verification</label>
                                    <div className="flex items-center gap-2">
                                        {user.email_verified_at ? (
                                            <>
                                                <CheckCircle className="h-4 w-4 text-green-600" />
                                                <Badge variant="default" className="bg-green-100 text-green-800">
                                                    Verified
                                                </Badge>
                                            </>
                                        ) : (
                                            <>
                                                <XCircle className="h-4 w-4 text-red-600" />
                                                <Badge variant="destructive">
                                                    Not Verified
                                                </Badge>
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
                            </CardContent>
                        </Card>

                        {/* Update Profile */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Update Profile</CardTitle>
                                <CardDescription>
                                    Update your name and email address.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleProfileUpdate} className="space-y-4">
                                    <div>
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            type="text"
                                            value={profileForm.name}
                                            onChange={(e) => setProfileForm(prev => ({ ...prev, name: e.target.value }))}
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={profileForm.email}
                                            onChange={(e) => setProfileForm(prev => ({ ...prev, email: e.target.value }))}
                                            required
                                        />
                                    </div>
                                    <Button type="submit" disabled={profileLoading} className="cursor-pointer">
                                    {profileLoading ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : null}
                                    Update Profile
                                </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Change Password */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Key className="h-5 w-5" />
                                Change Password
                            </CardTitle>
                            <CardDescription>
                                Update your account password for better security.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handlePasswordUpdate} className="space-y-4 max-w-md">
                                <div>
                                    <Label htmlFor="current_password">Current Password</Label>
                                    <div className="relative">
                                        <Input
                                            id="current_password"
                                            type={showPasswords.current ? "text" : "password"}
                                            value={passwordForm.current_password}
                                            onChange={(e) => setPasswordForm(prev => ({ ...prev, current_password: e.target.value }))}
                                            required
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="absolute right-0 top-0 h-full px-3"
                                            onClick={() => togglePasswordVisibility('current')}
                                        >
                                            {showPasswords.current ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </Button>
                                    </div>
                                </div>
                                <div>
                                    <Label htmlFor="password">New Password</Label>
                                    <div className="relative">
                                        <Input
                                            id="password"
                                            type={showPasswords.new ? "text" : "password"}
                                            value={passwordForm.password}
                                            onChange={(e) => setPasswordForm(prev => ({ ...prev, password: e.target.value }))}
                                            required
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="absolute right-0 top-0 h-full px-3"
                                            onClick={() => togglePasswordVisibility('new')}
                                        >
                                            {showPasswords.new ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </Button>
                                    </div>
                                </div>
                                <div>
                                    <Label htmlFor="password_confirmation">Confirm New Password</Label>
                                    <div className="relative">
                                        <Input
                                            id="password_confirmation"
                                            type={showPasswords.confirm ? "text" : "password"}
                                            value={passwordForm.password_confirmation}
                                            onChange={(e) => setPasswordForm(prev => ({ ...prev, password_confirmation: e.target.value }))}
                                            required
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="absolute right-0 top-0 h-full px-3"
                                            onClick={() => togglePasswordVisibility('confirm')}
                                        >
                                            {showPasswords.confirm ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </Button>
                                    </div>
                                </div>
                                <Button type="submit" disabled={passwordLoading} className="cursor-pointer">
                                    {passwordLoading ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : null}
                                    Update Password
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    {/* API Token Management */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Key className="h-5 w-5" />
                                API Token Management
                            </CardTitle>
                            <CardDescription>
                                Generate API tokens for programmatic access to your data.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Generate New Token */}
                            <div>
                                <h4 className="text-sm font-medium mb-3">Generate New Token</h4>
                                <div className="space-y-4">
                                    {existingTokens.length === 0 && (
                                        <form onSubmit={handleGenerateToken} className="space-y-4">
                                            <p className="text-sm text-gray-600 mb-4">
                                                Generate a new API token for accessing the API. You can only have one active token at a time.
                                            </p>
                                            <Button type="submit" disabled={tokenLoading} className="cursor-pointer">
                                                {tokenLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Generate API Token'}
                                            </Button>
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
                                        <Input
                                            type={showToken ? "text" : "password"}
                                            value={generatedToken}
                                            readOnly
                                            className="font-mono text-xs"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setShowToken(!showToken)}
                                            className="cursor-pointer"
                                        >
                                            {showToken ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => copyToClipboard(generatedToken)}
                                            className="cursor-pointer"
                                        >
                                            <Copy className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            )}

                            <Separator />

                            {/* Existing API Tokens */}
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
                                                <Button
                                                    onClick={() => handleRevokeToken(token.id)}
                                                    disabled={revokeLoading}
                                                    variant="destructive"
                                                    size="sm"
                                                    className="cursor-pointer"
                                                >
                                                    {revokeLoading ? (
                                                        <Loader2 className="w-3 h-3 animate-spin" />
                                                    ) : (
                                                        'Revoke'
                                                    )}
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-gray-500 text-center py-4">No API tokens found</p>
                                )}
                            </div>

                            <Separator />

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
                        </CardContent>
                    </Card>
                </div>
            </div>
        </DashboardLayout>
    );
}