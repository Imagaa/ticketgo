import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom'; // Tambah useSearchParams
import { getOrders, createPayment } from '../services/api';
import api from '../services/api'; // Import instance API untuk call custom
import { useAuth } from '../context/AuthContext';
import toast from 'react-hot-toast';

const Dashboard = () => {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams(); // Untuk baca URL ?check_payment=true
  const { user } = useAuth();
  
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [paymentLoading, setPaymentLoading] = useState(null);

  // 1. Fetch Orders & Auto Sync jika perlu
  useEffect(() => {
    const initDashboard = async () => {
      try {
        // Load Order List
        const response = await getOrders();
        let currentOrders = response.data.data;

        // LOGIC BARU: Auto-Sync jika user balik dari Midtrans
        const shouldCheck = searchParams.get('check_payment');
        if (shouldCheck) {
            // Cari order yang masih pending
            const pendingOrder = currentOrders.find(o => o.status === 'pending');
            if (pendingOrder) {
                toast.loading('Checking payment status...', { id: 'sync' });
                // Panggil endpoint baru kita
                await api.get(`/payment/${pendingOrder.order_number}/check`);
                toast.success('Status updated!', { id: 'sync' });
                
                // Reload order lagi biar status berubah jadi PAID
                const updatedResponse = await getOrders();
                currentOrders = updatedResponse.data.data;
            }
            // Bersihkan URL
            navigate('/dashboard', { replace: true });
        }

        setOrders(currentOrders);
      } catch (err) {
        console.error('Failed to load dashboard:', err);
      } finally {
        setLoading(false);
      }
    };

    initDashboard();
  }, [searchParams, navigate]);

  const handlePayment = async (orderNumber) => {
    setPaymentLoading(orderNumber);
    try {
      const response = await createPayment(orderNumber);
      const { snap_token } = response.data;
      
      if (window.snap) {
        window.snap.pay(snap_token, {
          onSuccess: () => {
            // Redirect manual agar trigger Auto-Sync di atas
            window.location.href = '/dashboard?check_payment=true';
          },
          onPending: () => {
             window.location.href = '/dashboard?check_payment=true';
          },
          onError: () => toast.error('Payment failed'),
          onClose: () => setPaymentLoading(null)
        });
      }
    } catch (error) {
      toast.error('Payment system error');
      setPaymentLoading(null);
    }
  };

  // Helper Functions
  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
      year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });
  };

  const formatPrice = (price) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(price);

  const getStatusColor = (status) => {
    const colors = { pending: 'bg-yellow-100 text-yellow-800', paid: 'bg-green-100 text-green-800', expired: 'bg-gray-100 text-gray-800' };
    return colors[status] || 'bg-gray-100 text-gray-800';
  };

  if (loading) return <div className="p-10 text-center">Loading...</div>;

  return (
    <div className="min-h-screen bg-gray-50 py-12">
      <div className="max-w-7xl mx-auto px-4">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">My Orders</h1>
          <p className="text-gray-600">Welcome, {user?.name}</p>
        </div>

        {orders.length === 0 ? (
          <div className="bg-white p-12 text-center rounded-lg shadow">
            <h2 className="text-xl font-bold">No orders yet</h2>
            <button onClick={() => navigate('/')} className="mt-4 bg-indigo-600 text-white px-6 py-2 rounded">Browse Events</button>
          </div>
        ) : (
          <div className="space-y-6">
            {orders.map((order) => (
              <div key={order.id} className="bg-white rounded-lg shadow overflow-hidden">
                <div className="bg-gray-50 px-6 py-4 border-b flex justify-between">
                   <span className="font-bold">#{order.order_number}</span>
                   <span className={`px-3 py-1 rounded-full text-xs font-bold ${getStatusColor(order.status)}`}>
                     {order.status.toUpperCase()}
                   </span>
                </div>
                
                <div className="p-6 grid md:grid-cols-3 gap-6">
                  <div className="md:col-span-2 flex gap-4">
                    {/* FIX GAMBAR: Pakai default Unsplash jika placeholder rusak */}
                    <img 
                      src={order.event?.banner_url || "https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?auto=format&fit=crop&q=80"}
                      className="w-32 h-24 object-cover rounded-lg bg-gray-200"
                      alt="Event"
                    />
                    <div>
                      <h3 className="text-xl font-bold">{order.event?.title}</h3>
                      <p className="text-gray-600">📍 {order.event?.location_name}</p>
                      <p className="text-sm text-gray-500 mt-2">{formatDate(order.created_at)}</p>
                    </div>
                  </div>

                  <div className="flex flex-col justify-center border-l pl-6">
                     <div className="mb-4">
                        <p className="text-gray-600">Total Amount</p>
                        <p className="text-2xl font-bold text-indigo-600">{formatPrice(order.total_amount)}</p>
                     </div>

                     {order.status === 'pending' ? (
                       <button 
                         onClick={() => handlePayment(order.order_number)}
                         disabled={paymentLoading === order.order_number}
                         className="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700 disabled:opacity-50"
                       >
                         {paymentLoading === order.order_number ? 'Processing...' : 'Pay Now'}
                       </button>
                     ) : (
                       <div className="text-center text-green-600 font-bold border border-green-200 bg-green-50 py-2 rounded">
                         ✅ Payment Successful
                       </div>
                     )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

export default Dashboard;