const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

export default function ShopHours({ hours, className = "" }) {
  return (
    <dl className={className}>
      {days.map((day, index) => (
        <div key={day}>
          <dt>{day}</dt>
          <dd>{Array.isArray(hours[index + 1]) ? hours[index + 1].join("–") : "Closed"}</dd>
        </div>
      ))}
    </dl>
  );
}
