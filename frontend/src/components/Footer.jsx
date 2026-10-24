const Footer = () => {
  return (
    <footer className="bg-gray-800 text-white mt-auto">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div>
            <h3 className="text-xl font-bold mb-4">🎫 TicketGo</h3>
            <p className="text-gray-400 text-sm">
              Platform pemesanan tiket event terpercaya di Indonesia.
            </p>
          </div>

          <div>
            <h3 className="text-lg font-semibold mb-4">Quick Links</h3>
            <ul className="space-y-2 text-sm">
              <li>
                <a href="/" className="text-gray-400 hover:text-white transition">
                  Browse Events
                </a>
              </li>
              <li>
                <a href="/dashboard" className="text-gray-400 hover:text-white transition">
                  My Orders
                </a>
              </li>
            </ul>
          </div>

          <div>
            <h3 className="text-lg font-semibold mb-4">Contact Us</h3>
            <ul className="space-y-2 text-sm text-gray-400">
              <li>📧 support@ticketgo.com</li>
              <li>📱 +62 812-3456-7890</li>
              <li>📍 Jakarta, Indonesia</li>
            </ul>
          </div>
        </div>

        <div className="border-t border-gray-700 mt-8 pt-6 text-center">
          <p className="text-gray-400 text-sm">
            © 2026 TicketGo. All rights reserved.
          </p>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
