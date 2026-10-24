import { Link } from 'react-router-dom';

const EventCard = ({ event }) => {
  // Helper: Format Tanggal (Dari start_time)
  const formatDate = (dateString) => {
    if (!dateString) return 'TBA';
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  };

  // Helper: Format Jam (Ambil dari start_time)
  const formatTime = (dateString) => {
    if (!dateString) return 'TBA';
    const date = new Date(dateString);
    return date.toLocaleTimeString('id-ID', {
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  // Helper: Format Harga
  const formatPrice = (price) => {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0,
    }).format(price);
  };

  // Logic: Cari harga termurah
  // Kita tambahkan '|| []' agar tidak error jika ticket_types kosong
  const minPrice =
    event.ticket_types && event.ticket_types.length > 0
      ? Math.min(...event.ticket_types.map((t) => parseFloat(t.price)))
      : 0;

  // Gambar Default (Pakai Unsplash agar lebih stabil daripada via.placeholder)
  const defaultImage = "https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?auto=format&fit=crop&q=80&w=400";

  return (
    <Link to={`/events/${event.slug}`}>
      <div className="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition-shadow duration-300 cursor-pointer h-full flex flex-col">
        {/* Bagian Gambar */}
        <div className="h-48 overflow-hidden relative bg-gray-200">
          <img
            // FIX 1: Gunakan 'banner_url' (backend baru), fallback ke defaultImage
            src={event.banner_url || defaultImage}
            // FIX 2: Gunakan 'title' (backend baru)
            alt={event.title}
            className="w-full h-full object-cover hover:scale-110 transition-transform duration-300"
            onError={(e) => { e.target.src = defaultImage; }} // Fallback jika gambar error
          />
          {/* Badge Status */}
          <div className="absolute top-2 right-2">
            <span className="bg-white/90 backdrop-blur text-indigo-600 text-xs font-bold px-2 py-1 rounded-full shadow">
              {event.status === 'published' ? 'On Sale' : event.status}
            </span>
          </div>
        </div>

        {/* Bagian Konten */}
        <div className="p-6 flex-1 flex flex-col justify-between">
          <div>
            {/* FIX 3: Gunakan 'title' */}
            <h3 className="text-xl font-bold text-gray-900 mb-2 line-clamp-2">
              {event.title}
            </h3>

            <div className="space-y-2 text-sm text-gray-600 mb-4">
              <div className="flex items-center">
                <span className="mr-2">📅</span>
                {/* FIX 4: Gunakan 'start_time' */}
                <span>{formatDate(event.start_time)}</span>
              </div>
              <div className="flex items-center">
                <span className="mr-2">🕐</span>
                {/* FIX 5: Gunakan 'start_time' untuk jam */}
                <span>{formatTime(event.start_time)} WIB</span>
              </div>
              <div className="flex items-center">
                <span className="mr-2">📍</span>
                {/* FIX 6: Gunakan 'location_name' */}
                <span className="line-clamp-1">
                  {event.location_name}, {event.city}
                </span>
              </div>
            </div>
          </div>

          <div className="flex justify-between items-center pt-4 border-t mt-auto">
            <div>
              <p className="text-xs text-gray-500">Starting from</p>
              <p className="text-lg font-bold text-indigo-600">
                {minPrice === 0 ? 'Free' : formatPrice(minPrice)}
              </p>
            </div>
            <span className="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
              View Details →
            </span>
          </div>
        </div>
      </div>
    </Link>
  );
};

export default EventCard;