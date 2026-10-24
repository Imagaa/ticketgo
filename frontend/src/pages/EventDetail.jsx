import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { getEventBySlug, createOrder } from '../services/api';
import { useAuth } from '../context/AuthContext';
import toast from 'react-hot-toast';

// [NOTE] Navbar & Footer dihapus karena sudah ada di App.jsx

const EventDetail = () => {
  const { slug } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth(); // Pastikan pakai 'user' bukan 'isAuthenticated' agar konsisten
  
  const [event, setEvent] = useState(null);
  const [loading, setLoading] = useState(true);
  const [selectedTickets, setSelectedTickets] = useState({});
  const [processing, setProcessing] = useState(false);

  useEffect(() => {
    // Script Snap Midtrans
    const snapScript = "https://app.sandbox.midtrans.com/snap/snap.js";
    const clientKey = "Mid-client-bm1UIHZ9NBwbp1QW";
    
    if(!document.getElementById('snap-script')){
      const script = document.createElement('script');
      script.src = snapScript;
      script.id = 'snap-script';
      script.setAttribute('data-client-key', clientKey);
      script.async = true;
      document.body.appendChild(script);
    }
    fetchEvent();
  }, [slug]);

  const fetchEvent = async () => {
    try {
      const response = await getEventBySlug(slug);
      setEvent(response.data.data);
    } catch (error) {
      toast.error('Event not found');
      navigate('/');
    } finally {
      setLoading(false);
    }
  };

  const handleTicketChange = (ticketId, delta, maxPerTrans, remainingQuota) => {
    const realLimit = Math.min(maxPerTrans || 5, remainingQuota); 
    const currentQty = selectedTickets[ticketId] || 0;
    const newQty = currentQty + delta;

    if (newQty >= 0 && newQty <= realLimit) {
      setSelectedTickets(prev => ({ ...prev, [ticketId]: newQty }));
    }
  };

  const calculateTotal = () => {
    if (!event) return 0;
    return event.ticket_types.reduce((total, ticket) => {
      const qty = selectedTickets[ticket.id] || 0;
      return total + (ticket.price * qty);
    }, 0);
  };

  const handleCheckout = async () => {
    if (!user) {
      toast.error('Please login to buy tickets');
      navigate('/login');
      return;
    }

    const items = Object.entries(selectedTickets)
      .filter(([_, qty]) => qty > 0)
      .map(([id, qty]) => ({ id: parseInt(id), qty }));

    if (items.length === 0) return;

    setProcessing(true);
    try {
      const response = await createOrder({ event_id: event.id, tickets: items });
      const { snap_token } = response.data.data;

      if (window.snap) {
        window.snap.pay(snap_token, {
          onSuccess: () => { toast.success('Payment Success!'); navigate('/dashboard'); },
          onPending: () => { toast('Waiting for payment'); navigate('/dashboard'); },
          onError: () => { toast.error('Payment Failed'); }
        });
      }
    } catch (error) {
      console.error(error);
      toast.error('Checkout failed');
    } finally {
      setProcessing(false);
    }
  };

  if (loading) return <div className="p-10 text-center">Loading event...</div>;
  if (!event) return null;

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Navbar dihapus disini */}
      
      <div className="relative h-96 bg-gray-900">
        <img 
          src={event.banner_url || "https://images.unsplash.com/photo-1533174072545-e8d4aa97edf9?auto=format&fit=crop&q=80"}
          alt={event.title}
          className="w-full h-full object-cover opacity-60"
        />
        <div className="absolute inset-0 flex items-end pb-12">
           <div className="max-w-7xl mx-auto px-4 w-full text-white">
              <h1 className="text-4xl font-bold mb-2">{event.title}</h1>
              <p className="text-xl">📍 {event.location_name}, {event.city}</p>
           </div>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 py-12 grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div className="lg:col-span-2 bg-white p-8 rounded-lg shadow-sm">
          <h2 className="text-2xl font-bold mb-4">Description</h2>
          <p className="text-gray-600">{event.description}</p>
        </div>

        <div className="bg-white p-6 rounded-lg shadow-lg h-fit sticky top-24">
          <h3 className="text-xl font-bold mb-6">Select Tickets</h3>
          <div className="space-y-4">
            {event.ticket_types.map((ticket) => {
              const remaining = ticket.quota_total - ticket.quota_sold;
              const maxLimit = ticket.max_per_transaction || 5;
              const canAdd = (selectedTickets[ticket.id] || 0) < Math.min(remaining, maxLimit);
              
              return (
                <div key={ticket.id} className="border p-4 rounded-lg">
                  <div className="flex justify-between mb-2">
                    <span className="font-semibold">{ticket.name}</span>
                    <span className="text-indigo-600 font-bold">
                      Rp {new Intl.NumberFormat('id-ID').format(ticket.price)}
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-500">{remaining} left</span>
                    <div className="flex items-center space-x-3">
                      <button 
                        onClick={() => handleTicketChange(ticket.id, -1, maxLimit, remaining)}
                        className="w-8 h-8 rounded-full bg-gray-200 hover:bg-gray-300"
                        disabled={(selectedTickets[ticket.id] || 0) <= 0}
                      >-</button>
                      <span className="w-6 text-center">{selectedTickets[ticket.id] || 0}</span>
                      <button 
                        onClick={() => handleTicketChange(ticket.id, 1, maxLimit, remaining)}
                        className="w-8 h-8 rounded-full bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled={!canAdd}
                      >+</button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>

          <div className="mt-6 pt-6 border-t">
            <div className="flex justify-between mb-4 font-bold text-lg">
              <span>Total</span>
              <span>Rp {new Intl.NumberFormat('id-ID').format(calculateTotal())}</span>
            </div>
            <button
              onClick={handleCheckout}
              disabled={processing || calculateTotal() === 0}
              className="w-full bg-indigo-600 text-white py-3 rounded-lg font-bold hover:bg-indigo-700 disabled:opacity-50"
            >
              {processing ? 'Processing...' : 'Checkout Now'}
            </button>
          </div>
        </div>
      </div>
      
      {/* Footer dihapus disini */}
    </div>
  );
};

export default EventDetail;