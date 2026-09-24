import { Link } from "react-router-dom";

import "./AboutSection.scss";

const AboutSection = () => {
  return (
    <section className="about-section">
      <div className="container about-section__container">

        <div className="about-section__image">
          Barber Shop Image
        </div>

        <div className="about-section__content">

          <span className="section-label">
            About Forge & Fade
          </span>

          <h2 className="section-title">
            Good Hair.
            <br />
            Good Service.
          </h2>

          <p>
            Forge & Fade is a modern barbershop focused on
            quality cuts, clean fades and professional grooming.
          </p>

          <p>
            Our goal is simple: provide every customer with
            a comfortable experience and a haircut they feel
            confident wearing.
          </p>

          <div className="about-section__points">

            <div>
              <strong>Quality</strong>
              <span>Professional barbering</span>
            </div>

            <div>
              <strong>Comfort</strong>
              <span>Relaxed environment</span>
            </div>

            <div>
              <strong>Convenience</strong>
              <span>Easy online booking</span>
            </div>

          </div>

          <Link
            to="/about"
            className="btn btn-primary"
          >
            Learn More
          </Link>

        </div>

      </div>
    </section>
  );
};

export default AboutSection;