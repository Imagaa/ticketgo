const SkeletonCard = () => {
  return (
    <div className="bg-white rounded-lg shadow-md overflow-hidden animate-pulse">
      {/* Image skeleton */}
      <div className="h-48 bg-gray-300"></div>

      {/* Content skeleton */}
      <div className="p-6">
        <div className="h-6 bg-gray-300 rounded mb-4"></div>
        <div className="space-y-2 mb-4">
          <div className="h-4 bg-gray-200 rounded w-3/4"></div>
          <div className="h-4 bg-gray-200 rounded w-2/3"></div>
          <div className="h-4 bg-gray-200 rounded w-1/2"></div>
        </div>
        <div className="flex justify-between items-center pt-4 border-t">
          <div className="h-6 bg-gray-300 rounded w-1/3"></div>
          <div className="h-10 bg-gray-300 rounded w-1/4"></div>
        </div>
      </div>
    </div>
  );
};

export default SkeletonCard;
