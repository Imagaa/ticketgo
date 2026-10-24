import { createContext, useState, useEffect, useContext } from 'react';
import { getMe } from '../services/api';

const AuthContext = createContext();

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return context;
};

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  
  // Ambil token dari localStorage saat pertama kali load
  const [token, setToken] = useState(() => localStorage.getItem('token'));

  useEffect(() => {
    const fetchUser = async () => {
      // Jika tidak ada token, set loading false dan stop
      if (!token) {
        setUser(null);
        setLoading(false);
        return;
      }

      try {
        // Panggil API /me
        const response = await getMe();
        
        // [FIX UTAMA DISINI]
        // Backend mengirim format: { success: true, data: { ...user info... } }
        // Jadi kita harus mengambil response.data.data
        setUser(response.data.data); 

      } catch (error) {
        console.error('Failed to fetch user:', error);
        
        // Jika token expired/invalid (Error 401), hapus token agar bersih
        if (error.response && error.response.status === 401) {
            localStorage.removeItem('token');
            setToken(null);
            setUser(null);
        }
      } finally {
        setLoading(false);
      }
    };

    fetchUser();
  }, [token]);

  const loginUser = (userData, userToken) => {
    localStorage.setItem('token', userToken);
    setToken(userToken);
    setUser(userData);
  };

  const logoutUser = () => {
    localStorage.removeItem('token');
    setToken(null);
    setUser(null);
    // Opsional: Redirect atau refresh halaman jika perlu
    window.location.href = '/login'; 
  };

  const value = {
    user,
    token,
    loading,
    loginUser,
    logoutUser,
    isAuthenticated: !!user, // Bernilai true jika user object ada
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export default AuthContext;